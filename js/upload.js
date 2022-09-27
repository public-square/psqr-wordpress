class PSQRUpload {
    static uploadModal =
`
<div class="psqr-upload-modal js-psqr-upload-modal">
    <div class="psqr-upload-content">
        <span class="psqr-upload-close js-psqr-upload-close">&times;</span>
        <h1 class="js-psqr-upload-title">Upload File</h1>
        <form method="post" enctype="multipart/form-data" class="js-psqr-upload-form psqr-upload-form">
            <div class="psqr-upload-error js-psqr-upload-error">
                <h3>ERROR: <span class="js-psqr-error-msg"></span></h3>
                <h5>Please fix the error and upload it again</h5>
            </div>
            <div class="psqr-upload-warning js-psqr-upload-warning">
                <h3>Warning</h3>
                <h5>This is a warning</h5>
            </div>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="fileUpload" class="js-psqr-upload-label">Select a file to upload</label>
                        </th>
                        <td>
                            <input name="fileUpload" type="file" id="fileUpload" accept="application/json, application/did+json, .jwk, text/*">
                        </td>
                    </tr>
                    <tr class="js-psqr-pass psqr-hidden">
                        <th scope="row">
                            <label for="filePass" class="js-psqr-pass-label">Specify a file encryption password</label>
                        </th>
                        <td>
                            <input name="filePass" type="password" id="filePass">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="uploadBtn">Upload document</label></th>
                        <td><input class="button" id="uploadBtn" type="submit" value="Upload" onSubmit="PSQRUpload.uploadFile"></td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</div>
`;
    static readFileAsync(file) {
        return new Promise((resolve, reject) => {
            let reader = new FileReader();

            reader.onload = () => {
                resolve(reader.result);
            };

            reader.onerror = reject;

            reader.readAsText(file);
        })
    }

    static insertUploadModal() {
        const range = document.createRange();
        const documentFragment = range.createContextualFragment(this.uploadModal);

        document.body.appendChild(documentFragment);
    }

    static async uploadFile(form) {
        const data = form.dataset;
        const input = form.querySelector('#fileUpload');

        const file = input.files.item(0);
        const fileData = await this.readFileAsync(file);

        const url = `${data.path}?_wpnonce=${data.nonce}`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-type': 'application/did+json'
            },
            body: fileData
        });
        const resData = await response.json();

        if (response.status !== 200) {
            form.querySelector('div.js-psqr-upload-error').classList.add('visible');
            form.querySelector('span.js-psqr-error-msg').innerText = resData?.message || 'Unknown error';
            console.log(response);

            return false;
        }

        location.reload();

        return false;
    }

    static setup() {
        this.insertUploadModal();

        const didBtns = document.querySelectorAll('button.js-show-did-upload');
        for (let i = 0; i < didBtns.length; i++) {
            const btn = didBtns[i];

            btn.addEventListener('click', event => {
                event.preventDefault();

                // include relevant data
                const data = event.currentTarget.dataset;
                const form = document.querySelector('form.js-psqr-upload-form');

                // change text
                const title = 'Upload DID:PSQR';
                const formLabel = `Select did:psqr document for ${data.name}`
                document.querySelector('label.js-psqr-upload-label').innerText = formLabel;
                document.querySelector('h1.js-psqr-upload-title').innerText = title;

                // set form data
                form.dataset.type = 'did';
                form.dataset.name = data.name;
                form.dataset.nonce = data.nonce;
                form.dataset.path = data.path;

                // make modal visible
                const modal = document.querySelector('div.js-psqr-upload-modal');
                modal.classList.add("modal-visible");

                return false;
            })
        }

        const keyBtns = document.querySelectorAll('button.js-show-key-upload');
        for (let i = 0; i < keyBtns.length; i++) {
            const btn = keyBtns[i];

            btn.addEventListener('click', event => {
                event.preventDefault();

                // include relevant data
                const data = event.currentTarget.dataset;
                const form = document.querySelector('form.js-psqr-upload-form');

                // change text
                const title = 'Upload Private Key';
                const formLabel = `Select the <name>.private.jwk file containing a private key for ${data.did}`
                document.querySelector('label.js-psqr-upload-label').innerText = formLabel;
                document.querySelector('h1.js-psqr-upload-title').innerText = title;

                // change warning text
                const warningText = 'Private keys are stored in clear text. This reduces their security and could enable someone else to access them.'
                document.querySelector('div.js-psqr-upload-warning h5').innerText = warningText;
                document.querySelector('div.js-psqr-upload-warning').classList.add('visible');

                // show password field
                document.querySelector('tr.js-psqr-pass').classList.add('psqr-visible');

                // set form data
                form.dataset.type = 'key';
                form.dataset.did = data.did;
                form.dataset.nonce = data.nonce;
                form.dataset.path = data.path;

                // make modal visible
                const modal = document.querySelector('div.js-psqr-upload-modal');
                modal.classList.add("modal-visible");

                return false;
            })
        }

        const closeBtn = document.querySelector('span.js-psqr-upload-close');
        closeBtn.addEventListener('click', event => {
            // make modal hidden
            const modal = document.querySelector('div.js-psqr-upload-modal');
            modal.classList.remove("modal-visible");

            // remove error msg
            const errorMsg = document.querySelector('div.js-psqr-upload-error');
            errorMsg.classList.remove('visible');

            // remove warning msg
            const warningMsg = document.querySelector('div.js-psqr-upload-warning');
            warningMsg.classList.remove('visible');

            // reset form
            document.querySelector('tr.js-psqr-pass').classList.remove('psqr-visible');
            const form = document.querySelector('form.js-psqr-upload-form');
            form.reset();
        });

        const uploadForm = document.querySelector('form.js-psqr-upload-form');
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            // remove error msg
            const errorMsg = document.querySelector('div.js-psqr-upload-error');
            errorMsg.classList.remove('visible');

            await PSQRUpload.uploadFile(event.currentTarget);

            return false;
        })
    }
}

window.onload = () => {
    PSQRUpload.setup();
};

