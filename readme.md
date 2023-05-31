# Virtual Public Square WordPress Plugin
Requires WordPress version: 5.4+

Tested up to: 6.0.2

Requires PHP: 7.4

License: GPLv2

License URI: https://www.gnu.org/licenses/gpl-2.0.html

Virtual Public Squares operate on identity. Add self-hosted, cryptographically verifiable,
decentralized identity to your site and authors.

## Description

Virtual Public Squares are digital spaces for open communication. Publishers establish
identities, preferably on their own websites, and use those identities to attach
verifiable provenance to content with cryptographic signatures.  The signed content is
broadcast into one or more Virtual Public Squares. Once signed and broadcast, the content
becomes public and portable.

The content, with provenance, may move from one Virtual Public Square to another, may
be indexed and made searchable, and may be displayed to readers in feeds, curated lists,
or search results. Every time a Virtual Public Square displays content, the publisher's
cryptographic signature is attached to that content and can be verified.

In the event that any Virtual Public Square blocks content from a publisher, that
publisher remains free to broadcast that content to any other Virtual Public Square.
Similarly, publishers are free to set up their own Virtual Public Squares and ingest
the content from existing Virtual Public Squares. In this fashion, Virtual Public
Squares impose practical disincentives to censorship without infringing on the rights
of speech or association of any of the participants.

The primary use of Virtual Public Square technology is to acquire content from specified
publishers without third party interference. This will often appear in the format of a
news feed. The first commercial use of Virtual Public Square data is to populate widgets
on websites containing curated content from a set of publishers that seek to cross-promote
each other to keep readers engaged and maximize audience attention.

## Required PHP Extensions

The following PHP extensions are required to update and delete DIDs with an API call:

* json
* mbstring
* openssl

## API Endpoints

This plugin sets up some REST api endpoints to allow for the management of user DIDs.
Each endpoint uses the prefix `/wp-json/psqr/v{number}/`, where the version may increase in future releases.
Some are public and others require admin privileges. This is verified through an authentication cookie
and a Wordpress nonce that can be appended to the end of the url with `?_wpnonce={nonce}` or as a header
named `X-WP-Nonce`.

### Endpoints

---
**Current Prefix:** `/wp-json/psqr/v1/`

---

**GET** `/author/{name}`

Get the DID:PSQR document for the user specified

**Authentication:** none

**Returns:** JSON response
* Success(200): Full DID:PSQR document
* Error(404): json with error message

---

**PUT** `/author/{name}`

Update the DID:PSQR document for the user specified

**Authentication:** requires WP nonce and revised DID document in compact serialization
in request data

**Returns:** JSON response
* Success(200): json with success message
* Error(400): json with error message

---

**DELETE** `/author/{name}`

Delete the DID:PSQR document for the user specified

**Authentication:** requires revised DID document in compact serialization
in request body data

See: https://datatracker.ietf.org/doc/html/rfc7515#section-3.1

**Returns:** JSON response
* Success(200): json with success message
* Error(400): json with error message

## Frequently Asked Questions

### What are Decentralized IDentities (DIDs)?

Decentalized IDentities are files that contain identifying information and public keys used
to encrypt or sign tokens or content. Virtual Public Squares use the W3C Decentralized
IDentity standard to attach provenance to content for distribution. Specifically, Virtual
Public Squares use the `did:psqr` method.

Further information on the standards is available at https://vpsqr.com

### How do I create a did:psqr identity?

You can generate DID:PSQR docs in two ways, manually or using generation feature built for wordpress.

#### Wordpress generation feature.
Wordpress generation feature will create the keys and place them in the correct locations.

#### Manual via command line feature.

Manually using the NodeJS command "psqr" (https://www.npmjs.com/package/psqr).
Install "psqr" with the command

`npm i -g psqr`

Then run

`psqr identity:create did:psqr:{domain}/author/{name} --name="Full Name"`

The identity and public keys are visible with this command

`psqr identity did:psqr:{domain}/author/{name} --all --verbose`

Save the `didDoc` section as a JSON file.

Note that your private keys are stored in your home directory.

### How do I upload an identity document?

Log in as an Admin and navigate to the user settings page.

For each user that doesn't have an identity document associated with them yet there should
be an "Upload" button in the "DID" column. ("DID" means Decentralized IDentity.)

Click the upload button and a modal dialog appears to select a file from your computer.

Selection the appropriate json file and then click "Upload" in the modal.

If there are any issues with the identity, like it is invalid or for a different person,
a red alert section is displayed describing the issue. You can select another document
and attempt it again.

### Can Virtual Public Square Content be Deleted?

Virtual Public Squares support two levels of content deletion - soft and hard deletes.
Soft deletes are requested, and hard deletes are strongly recommended, typically for
legal reasons. Those operating Virtual Public Squares are expected to delete any copies
of content in either delete list.

Wordpress functionality provides a delete button that does a 'hard' delete removal of information.

The internet is forever, though, and it is not possible to ensure that every copy of a
portable file has been deleted. Provenance travels with the content, and is thus also
potentially preserved by someone, somewhere.

### Screenshots

1. DID column show in the user management table
![user management table](assets/screenshot-1.png?raw=true "Title")
2. DID upload modal
![upload modal](assets/screenshot-2.png?raw=true "Title")

### Changelog
#### 0.1.5
* Added the ability to generate, upload and delete private keys

#### 0.1.4
* Added the ability to store and retrieve private keys

#### 0.1.3
* Added support for Wordpress installs that are not in the root domain

#### 0.1.2
* Update token format to {token: JWSstring}
* Add checks for required php extensions
* Add debug logs

#### 0.1.1
* Add ability to update DIDs with PUT request and delete DIDs with DELETE

#### 0.1.0
* Initial release
