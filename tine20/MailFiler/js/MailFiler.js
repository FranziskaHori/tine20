/*
 * Tine 2.0
 *
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 */

Ext.ns('Tine.MailFiler');

/**
 * @namespace Tine.MailFiler
 * @class Tine.MailFiler.Application
 * @extends Tine.Tinebase.Application
 */
Tine.MailFiler.Application = Ext.extend(Tine.Tinebase.Application, {
    /**
     * Get translated application title of this application
     *
     * @return {String}
     */
    getTitle : function() {
        return this.i18n.gettext('MailFiler');
    }
});

/*
 * register additional action for genericpickergridpanel
 */
Tine.widgets.relation.MenuItemManager.register('MailFiler', 'Node', {
    text: 'Save locally',   // i18n._('Save locally')
    iconCls: 'action_filemanager_save_all',
    requiredGrant: 'readGrant',
    actionType: 'download',
    allowMultiple: false,
    handler: function(action) {
        var node = action.grid.store.getAt(action.gridIndex).get('related_record');
        var downloadPath = node.path;
        var downloader = new Ext.ux.file.Download({
            params: {
                method: 'MailFiler.downloadFile',
                requestType: 'HTTP',
                id: '',
                path: downloadPath
            }
        }).start();
    }
});

/**
 * @namespace Tine.MailFiler
 * @class Tine.MailFiler.MainScreen
 * @extends Tine.widgets.MainScreen
 */
Tine.MailFiler.MainScreen = Ext.extend(Tine.widgets.MainScreen, {
    activeContentType: 'Node'
});
