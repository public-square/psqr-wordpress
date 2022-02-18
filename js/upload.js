class DIDUpload {
    static uploadModal = 
`
<div class="did-upload-modal js-did-upload-modal">
    <div class="did-upload-content">
        <span class="did-upload-close js-did-upload-close">&times;</span>
        <h1>Upload DID:PSQR</h1>
        <form method="post" enctype="multipart/form-data" class="js-did-upload-form">
            <div class="did-upload-error js-did-upload-error">
                <h3>DID ERROR: <span class="js-did-error-msg"></span></h3>
                <h5>Please fix the error and upload it again</h5>
            </div>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="didUpload">Select did:psqr document for <span class="js-did-user"></span></label>
                        </th>
                        <td>
                            <input name="didUpload" type="file" id="didUpload" accept="application/json, application/did+json, text/*">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="uploadBtn">Upload document</label></th>
                        <td><input class="button" id="uploadBtn" type="submit" value="Upload" onSubmit="DIDUpload.uploadFile"></td>
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
        const input = form.querySelector('#didUpload');

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
            form.querySelector('div.js-did-upload-error').classList.add('error-visible');
            form.querySelector('span.js-did-error-msg').innerText = resData.message;
            console.log(response);

            return false;
        }

        location.reload();

        return false;
    }

    static setup() {
        this.insertUploadModal();

        const openBtns = document.querySelectorAll('button.js-show-did-upload');
        for (let i = 0; i < openBtns.length; i++) {
            const btn = openBtns[i];

            btn.addEventListener('click', event => {
                event.preventDefault();
                
                // include relevant data
                const data = event.currentTarget.dataset;
                const form = document.querySelector('form.js-did-upload-form');
                document.querySelector('span.js-did-user').innerText = data.name;
                form.dataset.name = data.name;
                form.dataset.nonce = data.nonce;
                form.dataset.path = data.path;

                // make modal visible
                const modal = document.querySelector('div.js-did-upload-modal');
                modal.classList.add("modal-visible");
        
                return false;
            })
        }

        const closeBtn = document.querySelector('span.js-did-upload-close');
        closeBtn.addEventListener('click', event => {
            // make modal hidden
            const modal = document.querySelector('div.js-did-upload-modal');
            modal.classList.remove("modal-visible");

            //remove error msg
            const errorMsg = document.querySelector('div.js-did-upload-error');
            errorMsg.classList.remove('error-visible');
        });

        const uploadForm = document.querySelector('form.js-did-upload-form');
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await DIDUpload.uploadFile(event.currentTarget);
    
            return false;
        })
    }
}

window.onload = () => {
    DIDUpload.setup();
};

