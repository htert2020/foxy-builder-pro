var FoxyApp = {};

FoxyApp.mediaUploader = null;

FoxyApp.Main = class {

    filePanelElement = null;

    init()
    {
        this.filePanelElement = document.querySelector('.foxybdrp-file-panel');

        document.querySelector('.foxybdrp-provider-select').addEventListener('change', this);
        document.querySelector('.foxybdrp-file-panel input').addEventListener('click', this);
        document.querySelector('.foxybdrp-file-panel .foxybdrp-upload').addEventListener('click', this);
        document.querySelector('.foxybdrp-file-panel .foxybdrp-clear').addEventListener('click', this);
        document.querySelector('#foxybdrp-save-button').addEventListener('click', this);

        let formElement = document.querySelector('#foxybdr-action-form');

        let zipIdStr = formElement.querySelector('input[name="foxybdr-zip-id"]').value;
        let zipFilename = formElement.querySelector('input[name="foxybdr-zip-filename"]').value;

        if (zipIdStr !== '')
        {
            let inputElement = this.filePanelElement.querySelector('input[type="text"]');
            inputElement.value = zipFilename;
            inputElement.setAttribute('foxybdrp-id', zipIdStr);

            this.filePanelElement.classList.add('foxybdrp-uploaded');
        }
    }

    handleEvent(e)
    {
        if (e.type === 'change')
        {
            if (e.currentTarget.classList.contains('foxybdrp-provider-select'))
                this.testZipFile();
        }
        else if (e.type === 'click')
        {
            if (e.currentTarget.tagName.toLowerCase() === 'input')
                this.onInputFieldClicked(e);
            else if (e.currentTarget.classList.contains('foxybdrp-upload'))
                this.onUploadButtonClicked(e);
            else if (e.currentTarget.classList.contains('foxybdrp-clear'))
                this.onClearButtonClicked(e);
            else if (e.currentTarget.id === 'foxybdrp-save-button')
                this.onSaveButtonClicked(e);
        }
    }

    onUploadButtonClicked(e)
    {
        this.runMediaUploader();
    }

    onInputFieldClicked(e)
    {
        this.runMediaUploader();
    }

    onClearButtonClicked(e)
    {
        let inputElement = this.filePanelElement.querySelector('input[type="text"]');
        inputElement.value = '';
        inputElement.removeAttribute('foxybdrp-id');

        this.filePanelElement.classList.remove('foxybdrp-uploaded');
    }

    runMediaUploader()
    {
        let self = this;

        let fileType = 'zip';

        let oldExtensions = _wpPluploadSettings.defaults.filters.mime_types[0].extensions;

        FoxyApp.mediaUploader = wp.media({
            title: FOXYAPP.dialogs.mediaUploader.title,
            button: {
                text: FOXYAPP.dialogs.mediaUploader.buttonText
            },
            multiple: false,
            library: {
                type: [ 'application/zip' ]
            }
        });

        FoxyApp.mediaUploader.on('open',
            function()
            {
                if (self.filePanelElement.classList.contains('foxybdrp-uploaded'))
                {
                    let inputElement = self.filePanelElement.querySelector('input[type="text"]');
                    let idStr = inputElement.getAttribute('foxybdrp-id');
    
                    FoxyApp.mediaUploader.state().get('selection').add(wp.media.attachment(idStr));
                }
            }
        );

        FoxyApp.mediaUploader.on('ready',
            function()
            {
                _wpPluploadSettings.defaults.filters.mime_types[0].extensions = fileType;
            }
        );

        FoxyApp.mediaUploader.on('select',
            function()
            {
                let attachment = FoxyApp.mediaUploader.state().get('selection').first().toJSON();

                let inputElement = self.filePanelElement.querySelector('input[type="text"]');
                inputElement.value = attachment.filename;
                inputElement.setAttribute('foxybdrp-id', String(attachment.id));

                self.filePanelElement.classList.add('foxybdrp-uploaded');

                self.testZipFile();

                //self.parent.onFileUploaded(self);
            }
        );

        FoxyApp.mediaUploader.on('close',
            function()
            {
                _wpPluploadSettings.defaults.filters.mime_types[0].extensions = oldExtensions;
            }
        );

        FoxyApp.mediaUploader.open();
    }

    testZipFile()
    {
        var self = this;

        if (this.filePanelElement.classList.contains('foxybdrp-uploaded') === false)
            return;

        FoxyBuilder.showWait(true);

        let inputElement = this.filePanelElement.querySelector('input[type="text"]');
        let zipIdStr = inputElement.getAttribute('foxybdrp-id');

        FoxyBuilder.Ajax.fetch('foxybdrp_custom-icons_test_zip', {
            'foxybdr-provider': document.querySelector('.foxybdrp-provider-select').value,
            'foxybdr-zip-id': zipIdStr,
            'nonce': FOXYAPP.nonce
        })
        .then(function(response) {
            if (response.ok)
                return response.json();
            else
                self.onTestZipFileFailed();
        })
        .then(function(data) {
            if (data.status === 'OK')
            {
                if (data.test_result.status === 'OK')
                    self.onTestZipFileSucceeded();
                else
                    self.onTestZipFileFailed(data.test_result.status_message);
            }
            else
                self.onTestZipFileFailed();
        })
        .finally(function() {
            FoxyBuilder.showWait(false);
        });
    }

    onTestZipFileSucceeded()
    {
        (new FoxyBuilder.Dialogs.Alert({
            title: FOXYAPP.dialogs.zipTest.success.title,
            message: FOXYAPP.dialogs.zipTest.success.message,
            okLabel: FOXYAPP.dialogs.zipTest.success.okLabel
        })).create();
    }

    onTestZipFileFailed(message)
    {
        (new FoxyBuilder.Dialogs.Alert({
            title: FOXYAPP.dialogs.zipTest.failure.title,
            message: FOXYAPP.dialogs.zipTest.failure.message + (message !== undefined ? ' ' + message : ''),
            okLabel: FOXYAPP.dialogs.zipTest.failure.okLabel
        })).create();

        this.onClearButtonClicked(null);
    }

    onSaveButtonClicked(e)
    {
        let title = document.querySelector('#foxybdrp-post-title').value.trim();

        if (title.length === 0)
        {
            (new FoxyBuilder.Dialogs.Alert({
                title: FOXYAPP.dialogs.validationError.missingTitle.title,
                message: FOXYAPP.dialogs.validationError.missingTitle.message,
                okLabel: FOXYAPP.dialogs.validationError.missingTitle.okLabel
            })).create();

            return;
        }

        if (this.filePanelElement.classList.contains('foxybdrp-uploaded') === false)
        {
            (new FoxyBuilder.Dialogs.Alert({
                title: FOXYAPP.dialogs.validationError.missingFileUpload.title,
                message: FOXYAPP.dialogs.validationError.missingFileUpload.message,
                okLabel: FOXYAPP.dialogs.validationError.missingFileUpload.okLabel
            })).create();

            return;
        }

        let inputElement = this.filePanelElement.querySelector('input[type="text"]');
        let zipIdStr = inputElement.getAttribute('foxybdrp-id');
        let zipFilename = inputElement.value;

        let formElement = document.querySelector('#foxybdr-action-form');
        formElement.querySelector('input[name="foxybdr-title"]').value = title;
        formElement.querySelector('input[name="foxybdr-provider"]').value = document.querySelector('.foxybdrp-provider-select').value;
        formElement.querySelector('input[name="foxybdr-zip-id"]').value = zipIdStr;
        formElement.querySelector('input[name="foxybdr-zip-filename"]').value = zipFilename;
        formElement.submit();
    }
};

var FOXY_APP_MAIN = new FoxyApp.Main();

window.addEventListener('load',
    function(e)
    {
        FOXY_APP_MAIN.init();
    }
);
