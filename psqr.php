<?php
/**
 * @package Virtual Public Square
 */
/*
Plugin Name: Virtual Public Square
Plugin URI: https://vpsqr.com/
Description: Virtual Public Squares operate on identity. Add self-hosted, cryptographically verifiable, decentralized identity to your site and authors.
Version: 0.1.2
Author: Virtual Public Square
Author URI: https://vpsqr.com
License: GPLv2
Text Domain: psqr
*/

/*
This plugin allows admins to upload DID:PSQR documents for their wordpress users and
resolves requests for the did:psqr bare domain (to /.well-known/psqr) and specific user profiles (to /author/{name}).
You can generate DID:PSQR docs using the NodeJS CLI client "psqr" (https://www.npmjs.com/package/psqr)
that can be installed with the command "npm i -g psqr".
Then go to the user settings page to upload user specific docs.
Your bare domain doc must be manually added to "uploads/psqr-identities/identity.json" after loading the user settings page once.
*/

require_once(plugin_dir_path(__FILE__) . '/lib/autoload.php');

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;

if ( ! function_exists('write_log')) {
    function write_log ( $log )  {
        $prefix = '|| Virtual Public Square || ';
        if ( is_array( $log ) || is_object( $log ) ) {
            error_log( $prefix . print_r( $log, true ) );
        } else {
            error_log( $prefix . $log );
        }
    }
}

if ( !class_exists( 'PSQR' ) ) {

    class PSQR
    {
        const VERSION = '1';
        // set headers that receive a json response
        const VALID_HEADERS = [
            'application/json',
            'application/did+json'
        ];

        // set required php extensions
        const REQUIRED_EXT = [
            'json',
            'mbstring',
            'openssl'
        ];

        // set some standard responses
        const RESPONSES = [
            'did_missing' => [
                'code'    => 'did_missing',
                'message' => 'The specified DID:PSQR identity could not be found',
                'data'    => [
                    'status' => 404
                ]
            ],
            'invalid_jws' => [
                'code'    => 'invalid_jws',
                'message' => 'The provided JWS was not signed correctly or is somehow invalid',
                'data'    => [
                    'status' => 401
                ]
            ],
            'did_error' => [
                'code'    => 'did_error',
                'message' => 'There was an error storing the did.',
                'data'    => [
                    'status' => 400
                ]
            ],
            'ext_missing' => [
                'code'    => 'ext_missing',
                'message' => 'The required php extensions are missing',
                'data'    => [
                    'status' => 500
                ]
            ],
        ];

        private $ext_missing = array();
        private $available_dids = array();
        private JWSSerializerManager $serializer_manager;
        private JWSVerifier $jws_verifier;

        function __construct() {
            // check for missing php extensions
            $this->ext_missing = $this->check_extensions();

            // create necessary directories
            $this->setup_dirs();

            $algorithmManager  = new AlgorithmManager([new ES384()]);
            $this->jws_verifier = new JWSVerifier(
                $algorithmManager
            );
            $this->serializer_manager = new JWSSerializerManager([
                new CompactSerializer(),
            ]);

            // add notice on admin page
            add_action('after_plugin_row', array($this, 'add_ext_warning'));

            // setup did and api response
            add_action('parse_request', array($this, 'rewrite_request'));
            add_action('rest_api_init', function () {
                $base = 'psqr/v' . $this::VERSION;
                register_rest_route( $base, '/author/(?P<name>[\w]+)', array(
                    array(
                        'methods' => WP_REST_Server::READABLE,
                        'callback' => array($this, 'api_get_response'),
                        'permission_callback' => '__return_true'
                    ),
                    array(
                        'methods' => WP_REST_Server::EDITABLE,
                        'callback' => array($this, 'api_put_response'),
                        'permission_callback' => function () {
                            return current_user_can('edit_users');
                        }
                    )
                ));
            });

            // setup did column in user table
            add_filter( 'manage_users_columns', array($this, 'add_did_column'));
            add_filter( 'manage_users_custom_column', array($this, 'add_did_value'), 10, 3 );
        }

        static function check_extensions(): array {
            $ext_missing = [];
            foreach (PSQR::REQUIRED_EXT as $ext) {
                if (extension_loaded($ext) === false) {
                    write_log('WARNING: missing php extension required for DID validation: ' . $ext);
                    array_push($ext_missing, $ext);
                }
            }

            return $ext_missing;
        }

        static function setup_dirs() {
            // determine dir path
            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'psqr-identities/';

            // create author_dir if necessary
            $author_dir = $base_dir . 'author/';
            if (is_dir($author_dir) === false) {
                $dir_resp = mkdir($author_dir, 0777, true);
                if ($dir_resp === false) {
                    write_log('ERROR: unable to create psqr-identities directory');
                    return false;
                }
            }

            return true;
        }

        function api_get_response($data) {
            $identity = $this->get_identity();
            if (isset($data['name'])) {
                $identity = $this->get_identity('/author/' . $data['name']);
            }

            if ($identity === false) {
                write_log('ERROR: Unable to find DID from provided data');
                write_log($data);
                wp_send_json(PSQR::RESPONSES['did_missing'], 404);
            }

            return new WP_REST_Response($identity);
        }

        function api_put_response($request) {
            $body = json_decode($request->get_body(), false);
            $name = $request->get_url_params()['name'];

            $request_did = $this->generate_did_string($name);

            if (isset($body->id) === false || $body->id !== $request_did) {
                write_log('ERROR: DID mismatch for ' . $request_did);
                wp_send_json([
                    'code'    => 'did_mismatch',
                    'message' => 'Incorrect did:psqr provided, must match: ' . $request_did,
                    'data'    => [
                        'status' => 400
                    ]
                ], 400);
            }

            // validate doc
            $valid_resp = $this->validate_identity($body);
            if ($valid_resp['valid'] === false) {
                write_log('ERROR: invalid DID ' . $valid_resp['message']);
                wp_send_json([
                    'code'    => 'did_invalid',
                    'message' => $valid_resp['message'],
                    'data'    => [
                        'status' => 400
                    ]
                ], 400);
            }

            $response = $this->store_identity('/author/' . $name, $body);
            if ($response === false) {
                write_log('ERROR: Unable to store identity for ' . $name);
                wp_send_json(PSQR::RESPONSES['did_error'], 400);
            }

            return new WP_REST_RESPONSE(['message' => 'did:psqr document successfully uploaded']);
        }

        // function to retrieve did file data as an object
        // don't pass a path to get base identity
        function get_identity($path = '') {
            // determine absolute file path
            $upload_dir = wp_upload_dir();
            $full_path = trailingslashit( $upload_dir['basedir'] ) . 'psqr-identities' . $path . '/identity.json';

            // ensure file exists
            if (file_exists($full_path) === false) {
                return false;
            }

            // retrieve and parse file data
            $file_data = file_get_contents($full_path);
            $identity_obj = json_decode($file_data, false);

            // return empty object if no data found
            if ($identity_obj === NULL) {
                return false;
            }

            return $identity_obj;
        }

        function validate_identity($identity) {
            // basic validation, need more thorough validation later
            if (isset($identity->psqr) === false ||
                isset($identity->psqr->publicIdentity) === false ||
                isset($identity->psqr->publicKeys) === false ||
                isset($identity->psqr->permissions) === false) {
                return [
                    'valid' => false,
                    'message' => 'Invalid DID:PSQR structure'
                ];
            }


            return [
                'valid' => true,
                'message' => 'Valid DID:PSQR structure'
            ];
        }

        function store_identity($path, $file_data) {
            // determine absolute file path
            $upload_dir = wp_upload_dir();
            $base_path = trailingslashit( $upload_dir['basedir'] ) . 'psqr-identities/';
            $full_path = $base_path . $path . '/';

            // create the directory if necessary
            if (is_dir($full_path) === false) {
                $dir_resp = mkdir($full_path);
                if ($dir_response === false) {
                    return false;
                }
            }

            return file_put_contents($full_path . 'identity.json', json_encode($file_data));
        }

        function delete_identity($path) {
            // determine absolute file path
            $upload_dir = wp_upload_dir();
            $base_path = trailingslashit( $upload_dir['basedir'] ) . 'psqr-identities/';
            $full_path = $base_path . $path . '/';

            // if dir doesn't exist return false
            if (is_dir($full_path) === false) {
                return false;
            }

            $rm_file = unlink($full_path . 'identity.json');
            if ($rm_file === false) {
                return false;
            }

            return rmdir($full_path);
        }

        /**
         * validate jws token string with pubkey from specified did.
         *
         * @param string $path path from request url
         * @param string $token jws token string
         *
         * @return bool is it valid
         */
        function validate_update(string $path, string $token): bool
        {
            $kid = NULL;
            $jws = '';
            try {
                $jws = $this->serializer_manager->unserialize($token);
                $kid = $jws->getSignatures()[0]->getProtectedHeader()['kid'];
            } catch (\Throwable $th) {
                write_log('ERROR: Unable to serialize JWS');
                return false;
            }

            // get didDoc specified in header
            $matches = NULL;
            preg_match('/did:psqr:[^\/]+([^#]+)/', $kid, $matches);
            $kidPath = $matches[1];

            // fail if path from signature doesn't match request path
            if ($path !== $kidPath) {
                write_log('ERROR: JWS signature path does not match request path');
                return false;
            }

            $didDoc = $this->get_identity($path);

            if ($didDoc === false) {
                write_log('ERROR: Unable to retrieve DID doc for JWS');
                return false;
            }

            // try to find valid public keys
            $keys   = $didDoc->psqr->publicKeys;
            $pubKey = false;

            for ($j = 0; $j < \count($keys); ++$j) {
                $k = $keys[$j];
                if ($k->kid === $kid) {
                    $pubKey = new JWK((array) $k);

                    break;
                }
            }
            // return false if no pubKey was found
            if ($pubKey === false) {
                write_log('ERROR: No pubkey matching ' . $kid . ' found in current DID doc');
                return false;
            }

            // verify key used has admin permission
            $perms    = $didDoc->psqr->permissions;
            $keyGrant = false;

            for ($i = 0; $i < \count($perms); ++$i) {
                $p = $perms[$i];
                if ($p->kid === $kid) {
                    $keyGrant = $p->grant;

                    break;
                }
            }
            // return false if no grant was found or doesn't contain admin
            if ($keyGrant === false || in_array('admin', $keyGrant) === false) {
                write_log('ERROR: No admin grant found for ' . $kid . ' in current DID doc');
                return false;
            }

            return $this->jws_verifier->verifyWithKey($jws, $pubKey, 0);
        }

        // setup action to return identity.json on request
        function rewrite_request($query) {
            $path = $query->request;
            $method = $_SERVER['REQUEST_METHOD'];

            error_log('----------------------------------------------------------------------------');
            write_log('INFO: Evaluating ' . $method . ' request to path ' . $path);

            // retrieve, sanitize, and validate JWS string if present
            $jws_matches = array();
            $raw_input = file_get_contents('php://input');

            $jws = '';
            $json_object = json_decode($raw_input, false);

            if ($json_object !== NULL) {
                preg_match('/[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/', $json_object->token, $jws_matches, PREG_UNMATCHED_AS_NULL);
                $jws = empty($jws_matches) ? '' : $json_object->token;
            }

            write_log('INFO: Parsed JWS from response: ' . $jws);

            $headers = array_change_key_case(array_map('strtolower', getallheaders()), CASE_LOWER);
            $accept_header = $headers['accept'];

            // ensure required php extensions exist for PUTs and DELETEs
            if ((strtoupper($method) === 'PUT' || strtoupper($method) === 'DELETE') && count($this->ext_missing) !== 0) {
                write_log('ERROR: there are some required php extensions missing from the current installation: ' . implode(", ", $this->ext_missing));
                $error_response = PSQR::RESPONSES['ext_missing'];
                $error_response['message'] = $error_response['message'] . ': ' . implode(", ", $this->ext_missing);
                wp_send_json($error_response, 500);
            }

            if ($path === '.well-known/psqr') {
                if (strtoupper($method) === 'PUT') {
                    return $this->update_did('', $jws);
                }

                if (strtoupper($method) === 'DELETE') {
                    return $this->delete_did('', $jws);
                }

                $identity = $this->get_identity();

                if ($identity === false) {
                    wp_send_json(PSQR::RESPONSES['did_missing'], 404);
                }

                wp_send_json($identity);
            } elseif (isset($query->query_vars['author_name'])) {
                $author_name = $query->query_vars['author_name'];
                $file_path = '/author/' . $author_name;

                if (strtoupper($method) === 'PUT') {
                    return $this->update_did($file_path, $jws);
                }

                if (strtoupper($method) === 'DELETE') {
                    return $this->delete_did($file_path, $jws);
                }

                foreach (PSQR::VALID_HEADERS as $val) {
                    if (strpos($accept_header, $val) !== false) {
                        $identity = $this->get_identity($file_path);

                        if ($identity === false) {
                            wp_send_json(PSQR::RESPONSES['did_missing'], 404);
                        }

                        wp_send_json($identity);
                    }
                }
            }

            return $query;
        }

        function update_did(string $path, string $body)
        {
            write_log('INFO: updating DID with path ' . $path);
            $signature_valid = $this->validate_update($path, $body);

            if ($signature_valid === false) {
                write_log('ERROR: Signature is not valid');
                wp_send_json(PSQR::RESPONSES['invalid_jws'], 401);
            }

            $jws = $this->serializer_manager->unserialize($body);
            $newDid = json_decode($jws->getPayload(), false);

            // validate doc
            $valid_resp = $this->validate_identity($newDid);
            if ($valid_resp['valid'] === false) {
                write_log('ERROR: DID from JWS is not valid');
                wp_send_json([
                    'code'    => 'did_invalid',
                    'message' => $valid_resp['message'],
                    'data'    => [
                        'status' => 400
                    ]
                ], 400);
            }

            $response = $this->store_identity($path, $newDid);
            if ($response === false) {
                write_log('ERROR: there was an issue storing the DID for ' . $path);
                wp_send_json(PSQR::RESPONSES['did_error'], 400);
            }

            write_log('INFO: update successful');
            wp_send_json($newDid, 200);
        }

        public function delete_did(string $path, string $body)
        {
            write_log('INFO: deleting DID with path ' . $path);
            $signature_valid = $this->validate_update($path, $body);

            if ($signature_valid === false) {
                write_log('ERROR: Signature is not valid');
                wp_send_json(PSQR::RESPONSES['invalid_jws'], 401);
            }

            $response = $this->delete_identity($path);
            if ($response === false) {
                write_log('ERROR: there was an error deleting the DID for ' . $path);
                wp_send_json(PSQR::RESPONSES['did_error'], 400);
            }

            write_log('INFO: delete successful');
            wp_send_json([
                'code'    => 'did_deleted',
                'message' => 'DID was successfully deleted',
            ], 200);
        }

        function generate_did_string($name) {
            $path = '/author/' . $name;
            $did = 'did:psqr:' . $_SERVER['HTTP_HOST'] . $path;

            return $did;
        }

        function add_ext_warning(string $plugin_file) {
            // add notice if necessary
            if ($plugin_file === 'psqr/psqr.php' && count($this->ext_missing) !== 0) {
                echo '<div class="notice notice-warning">
                        <p><strong>Virtual Public Square</strong></p>
                        <p>You are missing some required php extensions to manage DIDs. Please install the following: ' . implode(", ", $this->ext_missing) . '</p>
                    </div>';
            }
        }

        // add column to user table
        function add_did_column($column) {
            $column['did'] = 'DID';

            // scan the dir to see what dids are available
            $upload_dir = wp_upload_dir();
            $base_path = trailingslashit( $upload_dir['basedir'] ) . 'psqr-identities/author/';
            $current_dids = scandir($base_path);
            $this->available_dids = array_diff($current_dids, array('.', '..'));

            return $column;
        }

        function add_did_value($val, $column_name, $user_id) {
            if ($column_name === 'did') {
                // get user values
                $user = get_user_by('id', $user_id);
                $path = '/wp-json/psqr/v' . $this::VERSION . '/author/' . $user->user_login;
                $did = 'did:psqr:' . $_SERVER['HTTP_HOST'] . $path;

                // if identity dir is present, show link
                if (in_array($user->user_login, $this->available_dids)) {
                    return '<a href="' . $path . '" target="_blank">' . $did . '</a>';
                } else { // else provide input field to upload did
                    // get nonce and name
                    $nonce = wp_create_nonce( 'wp_rest' );
                    $name = $user->display_name;

                    // set button html
                    $btn_html = wp_enqueue_script('did-upload', plugins_url( "js/upload.js", __FILE__)) .
                    wp_enqueue_style('did-upload-style', plugins_url( "css/upload-modal.css", __FILE__)) . '
                        <button class="button js-show-did-upload"
                            data-name="' . $name . '"
                            data-nonce="' . $nonce . '"
                            data-path="' . $path . '"
                        />Upload DID</button>';

                    return $btn_html;
                }
            }

            return $val;
        }
    }
}

$GLOBALS['psqr'] = new PSQR();
