<?php
/**
 * @package Virtual Public Square
 */
/*
Plugin Name: Virtual Public Square
Plugin URI: https://vpsqr.com/
Description: Virtual Public Squares operate on identity. Add self-hosted, cryptographically verifiable, decentralized identity to your site and authors.
Version: 0.1.0
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

if ( !class_exists( 'PSQR' ) ) {
    class PSQR
    {
        const VERSION = '1';
        // set headers that receive a json response
        const VALID_HEADERS = [
            'application/json',
            'application/did+json'
        ];

        private $available_dids = [];

        function __construct() {

            // create necessary directories
            $this->setup_dirs();

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

        static function setup_dirs() {
            // determine dir path
            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'psqr-identities/';

            // create author_dir if necessary
            $author_dir = $base_dir . 'author/';
            if (is_dir($author_dir) === false) {
                $dir_resp = mkdir($author_dir, 0777, true);
                if ($dir_resp === false) {
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
                wp_send_json([
                    'code'    => 'did_missing',
                    'message' => 'The specified DID:PSQR identity could not be found',
                    'data'    => [
                        'status' => 404
                    ]
                ], 404);
            }

            return new WP_REST_Response($identity);
        }

        function api_put_response($request) {
            $body = json_decode($request->get_body());
            $name = $request->get_url_params()['name'];

            $request_did = $this->generate_did_string($name);

            if (isset($body->id) === false || $body->id !== $request_did) {
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
                wp_send_json([
                    'code'    => 'did_error',
                    'message' => 'There was an error storing the did.',
                    'data'    => [
                        'status' => 400
                    ]
                ], 400);
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
            $identity_obj = json_decode($file_data);
    
            // return empty object if no data found
            if ($identity_obj === null) {
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
    
        // setup action to return identity.json on request
        function rewrite_request($query) {
            $path = $query->request;
    
            // get all headers and make all keys and values lowercase.
            $headers = array_change_key_case(array_map('strtolower', getallheaders()), CASE_LOWER);
            $accept_header = $headers['accept'];
    
            if ($path === '.well-known/psqr') {
                $identity = $this->get_identity();

                if ($identity === false) {
                    wp_send_json([
                        'code'    => 'did_missing',
                        'message' => 'The specified DID:PSQR identity could not be found',
                        'data'    => [
                            'status' => 404
                        ]
                    ], 404);
                }
    
                wp_send_json($identity);
            } elseif (isset($query->query_vars['author_name'])) {
                $author_name = $query->query_vars['author_name'];

                foreach (PSQR::VALID_HEADERS as $val) {
                    if (strpos($accept_header, $val) !== false) {
                        $file_path = '/author/' . $author_name;
                        $identity = $this->get_identity($file_path);

                        if ($identity === false) {
                            wp_send_json([
                                'code'    => 'did_missing',
                                'message' => 'The specified DID:PSQR identity could not be found',
                                'data'    => [
                                    'status' => 404
                                ]
                            ], 404);
                        }
    
                        wp_send_json($identity);
                    }
                }
            }
    
            return $query;
        }

        function generate_did_string($name) {
            $path = '/author/' . $name;
            $did = 'did:psqr:' . $_SERVER['HTTP_HOST'] . $path;

            return $did;
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
