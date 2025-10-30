var FoxyApp = {};

FoxyApp.Main = class {

    init()
    {
        let self = this;

        let actionElements = document.querySelectorAll('.foxybdr-admin-includes-table .foxybdr-row-actions span.foxybdr-link');

        for (let i = 0; i < actionElements.length; i++)
        {
            actionElements[i].addEventListener('click', function(e) { self.onTableAction(e); });
        }
    }

    onTableAction(e)
    {
        let action = e.currentTarget.getAttribute('foxybdr-action');

        let trElement = e.currentTarget.closest('tr');
        let postID = Number(trElement.getAttribute('foxybdr-post-id'));
        let postTitle = trElement.getAttribute('foxybdr-post-title');

        let actionFormElement = document.querySelector("#foxybdr-action-form");

        switch (action)
        {
            case 'delete':

                (new FoxyBuilder.Dialogs.Confirm({
                    title: FOXYAPP.dialogs.delete.title,
                    message: FOXYAPP.dialogs.delete.message + ` "${postTitle}"?`,
                    cancelLabel: FOXYAPP.dialogs.delete.cancelLabel,
                    confirmLabel: FOXYAPP.dialogs.delete.confirmLabel,
                    onCancel: null,
                    onConfirm: function()
                    {
                        actionFormElement.querySelector("input[name='foxybdr-action']").value = 'delete';
                        actionFormElement.querySelector("input[name='foxybdr-id']").value = String(postID);
                        actionFormElement.submit();
                    }
                })).create();

                break;
        }
    }
};

var FOXY_APP_MAIN = new FoxyApp.Main();

window.addEventListener('load',
    function(e)
    {
        FOXY_APP_MAIN.init();
    }
);
