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
			<div class="psqr-upload-info">
                <h3><a href='https://www.w3.org/TR/did-core/' target='new'>DID:PSQR</a>: 
                    This creates a digital identity that can be used to ensure provenance for the content you share.
                </h3>
            </div>
            <div class="psqr-upload-warning js-psqr-upload-warning">
				<h4>&nbsp;</h4>
                <h5>Your WordPress user profile is not changed by this feature. Existing DIDs will be replaced.</h5>
            </div>
            <table class="form-table" role="presentation">
                <tbody>
					<tr class="js-psqr-del">
						<th scope="row">
							<label for="fileDel" class="js-psqr-pass-label">Delete DID authentication</label> <br />
                        </th>
                        <td><input class="button" id="delBtn" type="submit" value="Delete" onSubmit="PSQRUpload.delDID"></td>
                    </tr>
					<tr class="js-psqr-gen">
						<th scope="row">
							<label for="fileFullName" class="js-psqr-pass-label" title="the name of the publisher">Publisher Name</label> <br />
							<label for="fileTagline" class="js-psqr-pass-label" title="a tagline or motto used by the publisher">Tagline</label> <br /><br />
							
							<label for="fileWebUrl" class="js-psqr-pass-label" title="website or profile page of the publisher">Website</label> <br />	
							<label for="fileImage" class="js-psqr-pass-label" title="logo or image of the publisher">Image</label> <br /><br />

							<label for="fileBio" class="js-psqr-pass-label" title="biographical information for a human publisher">Bio</label> <br />
							<label for="fileDesc" class="js-psqr-pass-label" title="describing the publisher">Description</label> <br />
                        </th>
                        <td>
							<input name="fileFullName" id="fileFullName"> <i>(required)</i>
                            <br /><input name="fileTagline" id="fileTagline"> <br />
                            <br /><input name="fileWebUrl" id="fileWebUrl" title="https://acme.com"> 
							<br /><input name="fileImage" id="fileImage" title="https://acme.com/images/pic.png"><br />
							<br /><textarea  name="fileBio" id="fileBio" size='40' type='text'></textarea> 
                            <br /><textarea  name="fileDesc" id="fileDesc" size='40' type='text'></textarea> 
                            <br /><br /><input class="button" id="generateBtn" type="submit" value="Generate" onSubmit="PSQRUpload.generateDID">
                        </td>
                    </tr>
                    <tr class="js-psqr-pass" >
						<th scope="row">
							<h2>Upload DID file</h2>
                            <label for="fileUpload" class="js-psqr-upload-label">Select did:psqr document to upload</label>
                        </th>
                        <td>
                            <input name="fileUpload" type="file" id="fileUpload" accept="application/json, application/did+json, .jwk, text/*"><br />
							<input class="button" id="uploadBtn" type="submit" value="Upload" onSubmit="PSQRUpload.uploadFile">
                        </td>
                    </tr>  
 					<tr class="js-psqr-pass2" >
						<th scope="row">
							<h2>Upload private key</h2>
                            <label for="fileUpload2" class="js-psqr-upload-label">Select the <name>.private.jwk file containing a private key</label>
                        </th>
                        <td>
                            <input name="fileUpload2" type="file" id="fileUpload2" accept="application/json, application/did+json, .jwk, text/*"><br />
							<input class="button" id="uploadBtn2" type="submit" value="Upload" onSubmit="PSQRUpload.uploadFile">
                        </td>
                    </tr>     
                </tbody>
            </table>
        </form>
		<div class="psqr-upload-disclaimer">
			<h3>Disclaimer:</h3>
			<h4>Private keys are stored in clear text but are not accessible or downloadable by any user. This information can be utilized by other plugins. 
			You accept the responsibility for securing and understanding plugins that implement this digital signature tool.
			This was the best option for a WordPress environment where server control is variable.</h4>
		</div>
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

	//build the modal and event triggers
    static insertUploadModal() {
        const range = document.createRange();
        const documentFragment = range.createContextualFragment(this.uploadModal);
        document.body.appendChild(documentFragment);
		
		//close clear form
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
			
			location.reload(); 
        });

		//upload file buttons
        const uploadForm = document.querySelector('form.js-psqr-upload-form');
        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
			
            // remove error msg
            const errorMsg = document.querySelector('div.js-psqr-upload-error');
            errorMsg.classList.remove('visible');
 
			if(event.submitter.id == 'delBtn'){
				await PSQRUpload.delDID(event.currentTarget);
				return false;
			} 
			
			if(event.submitter.id == 'generateBtn'){
				await PSQRUpload.generateDID(event.currentTarget);
			}else{
				await PSQRUpload.uploadFile(event.currentTarget, event.submitter.id);
			}
            return false;
        });
		
    }

	//json REST delete the DID: path triggers function call
	static async delDID(form) {
		var data = form.dataset;		
		var sndData = {
			'did': data.did,
			'nonce': data.nonce, 
			'path': data.path
		};

		const url = `${data.path}?_wpnonce=${data.nonce}`;
        const response = await fetch(url, {
            method: 'DELETE',
            body: sndData
        });
        const resData = await response.json(); 
		if (response.status !== 200) {
            form.querySelector('div.js-psqr-upload-error').classList.add('visible');
            form.querySelector('span.js-psqr-error-msg').innerText = resData?.message || 'Unknown error';
            console.log(response);

            return false;
        }
		
		// location.reload();
		// update the message area, hide the del feature
		document.querySelector('div.js-psqr-upload-warning h4').innerText = resData.message;
		document.querySelector('tr.js-psqr-del').classList.add('psqr-hidden');
		document.querySelector('tr.js-psqr-del').classList.remove('psqr-visible'); 		
		document.querySelector('div.js-psqr-upload-warning h4').classList.add('psqr-upload-content');
		return false;
    }
			
	//create a new DID:Private key: AJAX protocal
	static async generateDID(form) {
		var data = form.dataset;
		var fnam = form.querySelector('#fileFullName').value;
		if(fnam !== ''){
			var sndData = {
				'action': 'makeDID_action',
				'fullname': fnam,
				'bio': form.querySelector('#fileBio').value,
				'tagline': form.querySelector('#fileTagline').value,
				'desc': form.querySelector('#fileDesc').value,
				'weburl': form.querySelector('#fileWebUrl').value,
				'imgurl': form.querySelector('#fileImage').value,
				'name': data.name,
				'did': data.did,
				'nonce': data.nonce, 
				'path': data.path
			};

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajax_object.ajax_url, sndData, function(response) {
				document.querySelector('div.js-psqr-upload-warning h4').innerText = response;
				document.querySelector('div.js-psqr-upload-warning h4').classList.add('psqr-upload-content');
			});
		}else{
			document.querySelector('div.js-psqr-upload-warning h4').innerText ="FULLNAME IS REQUIRED";
		}	
		return false;
    }

	// upload the files: JOSN REST protocol: path determine function called
    static async uploadFile(form, fileBtn) {
        const data = form.dataset;
		const input = (fileBtn == 'uploadBtn')? form.querySelector('#fileUpload') : form.querySelector('#fileUpload2');
        const file = input.files.item(0);
		if(!file){
			form.querySelector('div.js-psqr-upload-error').classList.add('visible');
			form.querySelector('span.js-psqr-error-msg').innerText = 'No File';
			console.log('No File');
			return false;
		}
        const fileData = await this.readFileAsync(file);
		
		const url = (fileBtn == 'uploadBtn')?  `${data.path}?_wpnonce=${data.nonce}` :  `${data.keypath}?_wpnonce=${data.nonce}`;
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
		//show the results	 
		document.querySelector('div.js-psqr-upload-warning h4').innerText = resData.message;
		document.querySelector('div.js-psqr-upload-warning').classList.add('visible');
		document.querySelector('div.js-psqr-upload-warning h4').classList.add('psqr-upload-content');	
        return false;
    }

	//setup the modal and triggers
    static setup() {			
		const title = 'PSQR identity maintenance';
		
			//trigger the upload file functionality
        const didBtns = document.querySelectorAll('button.js-show-did-upload');
        for (let i = 0; i < didBtns.length; i++) {
            const btn = didBtns[i];

            btn.addEventListener('click', event => {
                event.preventDefault();			
				this.insertUploadModal(); 
			
                // include relevant data
                const data = event.currentTarget.dataset;
                const form = document.querySelector('form.js-psqr-upload-form');
			
                // change text
                var msg = (data.did != '')? 'The DID:kid will be ('+ data.did+')':'';
                document.querySelector('div.js-psqr-upload-warning h4').innerText = msg;
                document.querySelector('div.js-psqr-upload-warning').classList.add('visible');
			
                // set form data
                form.dataset.type = 'did';
				form.dataset.did = data.did;
                form.dataset.name = data.name;
                form.dataset.nonce = data.nonce;
                form.dataset.path = data.path;
			    form.dataset.keypath = data.keypath;
			
				// uses on form for all functionality: hide/show design based on assigned class
				document.querySelector('tr.js-psqr-del').classList.add('psqr-hidden');
			    document.querySelector('tr.js-psqr-del').classList.remove('psqr-visible');
				document.querySelector('h1.js-psqr-upload-title').innerText = title;
				document.querySelector('tr.js-psqr-gen').classList.remove('psqr-visible');
				document.querySelector('tr.js-psqr-gen').classList.add('psqr-hidden');
				document.querySelector('tr.js-psqr-pass').classList.remove('psqr-hidden');
				document.querySelector('tr.js-psqr-pass2').classList.remove('psqr-hidden');
			
                // make modal visible
                const modal = document.querySelector('div.js-psqr-upload-modal');
                modal.classList.add("modal-visible");

                return false;
            })
        }

		// trigger the delete functionality
		const delBtns = document.querySelectorAll('button.js-show-del-did');
        for (let i = 0; i < delBtns.length; i++) {
            const btn = delBtns[i];

            btn.addEventListener('click', event => {
                event.preventDefault();			
				this.insertUploadModal(); 
			
                // include relevant data
                const data = event.currentTarget.dataset;
                const form = document.querySelector('form.js-psqr-upload-form');
			
                // change text
                var msg = (data.did != '')? 'Remove the signature information for this DID:kid? ('+ data.did+')':'';
                document.querySelector('div.js-psqr-upload-warning h4').innerText = msg;
				document.querySelector('div.js-psqr-upload-warning h5').innerText = 'THIS ACTION CAN NOT BE UNDONE';
                document.querySelector('div.js-psqr-upload-warning').classList.add('visible');
			
                // set form data
                form.dataset.type = 'del';
				form.dataset.did = data.did;
                form.dataset.nonce = data.nonce;
                form.dataset.path = data.path;
			
				// update the message area, hide the other features
				document.querySelector('tr.js-psqr-del').classList.add('psqr-visible');
				document.querySelector('h1.js-psqr-upload-title').innerText = title;
				document.querySelector('tr.js-psqr-gen').classList.remove('psqr-visible');
				document.querySelector('tr.js-psqr-gen').classList.add('psqr-hidden');
				document.querySelector('tr.js-psqr-pass').classList.add('psqr-hidden');
				document.querySelector('tr.js-psqr-pass2').classList.add('psqr-hidden');
			
                // make modal visible
                const modal = document.querySelector('div.js-psqr-upload-modal');
                modal.classList.add("modal-visible");

                return false;
            })
        } 

		// trigger for the upload functionality
        const keyBtns = document.querySelectorAll('button.js-show-key-upload');
        for (let i = 0; i < keyBtns.length; i++) {
            const btn = keyBtns[i];

            btn.addEventListener('click', event => {
                event.preventDefault();		
				this.insertUploadModal(); 
				
                // include relevant data
                const data = event.currentTarget.dataset;
                const form = document.querySelector('form.js-psqr-upload-form');

                // change text
                var msg = (data.did != '')? 'The DID:kid will be ('+ data.did+')':'';
                document.querySelector('div.js-psqr-upload-warning h4').innerText = msg;
                document.querySelector('div.js-psqr-upload-warning').classList.add('visible');
				document.querySelector('h1.js-psqr-upload-title').innerText = title;

                // set form data
                form.dataset.type = 'key';
				form.dataset.name = data.name;
                form.dataset.did = data.did;
                form.dataset.nonce = data.nonce;
                form.dataset.path = data.path;
 				form.dataset.keypath = data.keypath; 
				form.querySelector('#fileWebUrl').value = data.url;
				form.querySelector('#fileImage').value = data.ava;
				form.querySelector('#fileFullName').value = data.disnam;
				form.querySelector('#fileBio').value = data.bio;
				form.querySelector('#fileTagline').value = data.tag;
			
				//hide uploads, show fields
				document.querySelector('tr.js-psqr-del').classList.add('psqr-hidden');
				document.querySelector('tr.js-psqr-del').classList.remove('psqr-visible');
				document.querySelector('tr.js-psqr-pass').classList.remove('psqr-visible');
				document.querySelector('tr.js-psqr-pass').classList.add('psqr-hidden');
				document.querySelector('tr.js-psqr-gen').classList.add('psqr-visible');
				document.querySelector('tr.js-psqr-gen').classList.remove('psqr-hidden'); 
				document.querySelector('tr.js-psqr-pass2').classList.add('psqr-hidden'); 
				document.querySelector('tr.js-psqr-pass2').classList.remove('psqr-visible');
				
                // make modal visible
                const modal = document.querySelector('div.js-psqr-upload-modal');
                modal.classList.add("modal-visible");
			
                return false;
            })
        }
    }
}

window.onload = () => {
    PSQRUpload.setup();
};

