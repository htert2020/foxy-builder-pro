var FoxyApp = {};

FoxyApp.mediaUploader = null;

FoxyApp.Main = class {

    #variationsElement = null;
    #variations = [];

    init()
    {
        let self = this;

        this.#variationsElement = document.querySelector('#foxybdrp-variations');

        document.querySelector('#foxybdrp-add-variation-button').addEventListener('click', this);
        document.querySelector('#foxybdrp-save-button').addEventListener('click', this);

        let formElement = document.querySelector('#foxybdr-action-form');

        document.querySelector('#foxybdrp-post-title').value = formElement.querySelector('input[name="foxybdr-title"]').value;

        let contentStr = formElement.querySelector('input[name="foxybdr-content"]').value;

        if (contentStr !== '')
        {
            let content = JSON.parse(contentStr);

            for (let variationData of content)
            {
                let doc = document.createElement('div');
                doc.innerHTML = document.querySelector('#foxybdrp-template-variation').text;
                let variationElement = doc.querySelector('.foxybdrp-variation');

                this.#variationsElement.appendChild(variationElement);

                let variation = new FoxyApp.Variation(this);
                variation.create(variationElement);
                variation.load(variationData);
                this.#variations.push(variation);
            }
        }

        if (this.#variations.length === 0 && formElement.querySelector('input[name="foxybdr-id"]').value === '')
        {
            this.onAddVariationButtonClicked(null);
        }
    }

    handleEvent(e)
    {
        if (e.type === 'click')
        {
            if (e.currentTarget.id === 'foxybdrp-add-variation-button')
                this.onAddVariationButtonClicked(e);
            else if (e.currentTarget.id === 'foxybdrp-save-button')
                this.onSaveButtonClicked(e);
        }
    }

    onAddVariationButtonClicked(e)
    {
        let doc = document.createElement('div');
        doc.innerHTML = document.querySelector('#foxybdrp-template-variation').text;
        let variationElement = doc.querySelector('.foxybdrp-variation');

        this.#variationsElement.appendChild(variationElement);

        let variation = new FoxyApp.Variation(this);
        variation.create(variationElement);
        this.#variations.push(variation);
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

        if (this.#variations.length === 0)
        {
            (new FoxyBuilder.Dialogs.Alert({
                title: FOXYAPP.dialogs.validationError.missingVariations.title,
                message: FOXYAPP.dialogs.validationError.missingVariations.message,
                okLabel: FOXYAPP.dialogs.validationError.missingVariations.okLabel
            })).create();

            return;
        }

        let success = true;

        for (let v of this.#variations)
        {
            if (v.validate() === false)
            {
                v.variationElement.classList.add('foxybdrp-validation-error');

                success = false;
            }
            else
            {
                v.variationElement.classList.remove('foxybdrp-validation-error');
            }
        }

        if (!success)
        {
            (new FoxyBuilder.Dialogs.Alert({
                title: FOXYAPP.dialogs.validationError.missingFileUpload.title,
                message: FOXYAPP.dialogs.validationError.missingFileUpload.message,
                okLabel: FOXYAPP.dialogs.validationError.missingFileUpload.okLabel
            })).create();

            return;
        }

        let content = [];
        for (let v of this.#variations)
            content.push(v.save());

        let formElement = document.querySelector('#foxybdr-action-form');
        formElement.querySelector('input[name="foxybdr-title"]').value = title;
        formElement.querySelector('input[name="foxybdr-content"]').value = JSON.stringify(content);
        formElement.submit();
    }

    onDestroyVariation(variation)
    {
        let newVariations = [];

        for (let v of this.#variations)
        {
            if (v !== variation)
                newVariations.push(v);
        }

        this.#variations = newVariations;
    }
};

FoxyApp.Variation = class {

    #parent = null;
    variationElement = null;
    filePanels = [];

    constructor(parent)
    {
        this.#parent = parent;
    }

    create(variationElement)
    {
        let self = this;

        this.variationElement = variationElement;

        let filePanelElements = this.variationElement.querySelectorAll('.foxybdrp-file-panel');
        for (let i = 0; i < filePanelElements.length; i++)
        {
            let filePanelElement = filePanelElements[i];

            let filePanel = new FoxyApp.FilePanel(this);
            filePanel.create(filePanelElement);
            this.filePanels.push(filePanel);
        }

        // click event: main upload button
        this.variationElement.querySelector('.foxybdrp-main-panel > div:nth-child(1) > button').addEventListener('click', this);

        // click event: variation close button
        this.variationElement.querySelector('.foxybdrp-main-panel > div:nth-child(2) > span').addEventListener('click', this);
    }

    handleEvent(e)
    {
        if (e.type === 'click')
        {
            if (e.currentTarget.tagName.toLowerCase() === 'button')
                this.onMainUploadButtonClicked(e);
            else if (e.currentTarget.tagName.toLowerCase() === 'span')
                this.onCloseButtonClicked(e);
        }
    }

    onMainUploadButtonClicked(e)
    {
        this.variationElement.classList.toggle('foxybdrp-open');
    }

    onCloseButtonClicked(e)
    {
        this.destroy();
    }

    validate()
    {
        let isUploaded = false;

        for (let filePanel of this.filePanels)
        {
            if (filePanel.isUploaded())
            {
                isUploaded = true;
                break;
            }
        }

        return isUploaded;
    }

    onFileUploaded(filePanel)
    {
        this.variationElement.classList.remove('foxybdrp-validation-error');
    }

    load(data)
    {
        this.variationElement.querySelector('.foxybdrp-weight-select').value = data.weight;
        this.variationElement.querySelector('.foxybdrp-style-select').value = data.style;

        for (let filePanel of this.filePanels)
        {
            let fileType = filePanel.getFileType();

            if (data.files[fileType] !== undefined)
                filePanel.load(data.files[fileType]);
        }
    }

    save()
    {
        let files = {};

        for (let filePanel of this.filePanels)
        {
            let fileType = filePanel.getFileType();
            files[fileType] = filePanel.save();
        }

        return {
            weight: this.variationElement.querySelector('.foxybdrp-weight-select').value,
            style: this.variationElement.querySelector('.foxybdrp-style-select').value,
            files: files
        };
    }

    destroy()
    {
        this.#parent.onDestroyVariation(this);

        for (let v of this.filePanels)
            v.destroy();

        this.filePanels = [];

        if (this.variationElement)
        {
            this.variationElement.querySelector('.foxybdrp-main-panel > div:nth-child(1) > button').removeEventListener('click', this);
            this.variationElement.querySelector('.foxybdrp-main-panel > div:nth-child(2) > span').removeEventListener('click', this);
            this.variationElement.remove();
        }

        this.variationElement = null;
    }
};

FoxyApp.FilePanel = class {

    parent = null;
    filePanelElement = null;

    constructor(parent)
    {
        this.parent = parent;
    }

    create(filePanelElement)
    {
        let self = this;

        this.filePanelElement = filePanelElement;

        // click event: file upload button
        this.filePanelElement.querySelector('button.foxybdrp-upload').addEventListener('click', this);

        // click event: input field
        this.filePanelElement.querySelector('input[type="text"]').addEventListener('click', this);

        // click event: clear button
        this.filePanelElement.querySelector('button.foxybdrp-clear').addEventListener('click', this);
    }

    handleEvent(e)
    {
        if (e.type === 'click')
        {
            if (e.currentTarget.classList.contains('foxybdrp-upload'))
                this.onUploadButtonClicked(e);
            else if (e.currentTarget.tagName.toLowerCase() === 'input')
                this.onInputFieldClicked(e);
            else if (e.currentTarget.classList.contains('foxybdrp-clear'))
                this.onClearButtonClicked(e);
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

        let fileType = this.filePanelElement.getAttribute('foxybdrp-file-type');
        let mimes = FOXYAPP.mimeTypes[fileType];
        let mimeList = mimes.split('|');

        let oldExtensions = _wpPluploadSettings.defaults.filters.mime_types[0].extensions;

        FoxyApp.mediaUploader = wp.media({
            title: FOXYAPP.dialogs.mediaUploader.title,
            button: {
                text: FOXYAPP.dialogs.mediaUploader.buttonText
            },
            multiple: false,
            library: {
                type: [ ...mimeList, mimeList.join('') ]
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

                self.parent.onFileUploaded(self);
            }
        );

        FoxyApp.mediaUploader.on('close',
            function()
            {
                _wpPluploadSettings.defaults.filters.mime_types[0].extensions = oldExtensions;
            }
        );

        FoxyApp.mediaUploader.open();

        FoxyApp.mediaUploader.uploader.uploader.param('foxybdrp-media-upload', 'foxybdrp-custom-fonts');
    }

    isUploaded()
    {
        return this.filePanelElement.classList.contains('foxybdrp-uploaded');
    }

    getFileType()
    {
        return this.filePanelElement.getAttribute('foxybdrp-file-type');
    }

    load(data)
    {
        let inputElement = this.filePanelElement.querySelector('input[type="text"]');

        if (data.id !== null)
        {
            inputElement.setAttribute('foxybdrp-id', String(data.id));
            inputElement.value = data.filename;
            this.filePanelElement.classList.add('foxybdrp-uploaded');
        }
        else
        {
            inputElement.removeAttribute('foxybdrp-id');
            inputElement.value = '';
            this.filePanelElement.classList.remove('foxybdrp-uploaded');
        }
    }

    save()
    {
        let inputElement = this.filePanelElement.querySelector('input[type="text"]');
        let idStr = inputElement.getAttribute('foxybdrp-id');

        return {
            id: idStr !== null ? Number(idStr) : null,
            filename: idStr !== null ? inputElement.value : null
        };
    }

    destroy()
    {
        this.filePanelElement.querySelector('button.foxybdrp-upload').removeEventListener('click', this);
        this.filePanelElement.querySelector('input[type="text"]').removeEventListener('click', this);
        this.filePanelElement.querySelector('button.foxybdrp-clear').removeEventListener('click', this);
        this.filePanelElement = null;
    }
}

var FOXY_APP_MAIN = new FoxyApp.Main();

window.addEventListener('load',
    function(e)
    {
        FOXY_APP_MAIN.init();
    }
);
