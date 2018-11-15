/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2018 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

Ext.ns('Tine.Filemanager');

/**
 * @namespace Tine.Filemanager
 * @class Tine.Filemanager.QuickLookRegistry
 * @singleton
 *
 * @todo think about adding a generalized registry / @see Tine.Tinebase.ExceptionHandlerRegistry and others
 * @todo move it to general registry to prevent x-windows problems?
 */
Tine.Filemanager.QuickLookRegistry = function() {
    return {

        items: {},
        
        /**
         * registers a handler
         * 
         * @param {String} contentType
         * @param {String} xtype panel xtype
         */
        register: function(contentType, xtype) {
            this.items[contentType] = xtype;
        },
        
        /**
         * returns a xtype for a contentType
         * 
         * @param {String} contentType
         * @return {String}
         */
        get: function(contentType) {
            if (this.items.hasOwnProperty(contentType)) {
                return this.items[contentType];
            }

            return null;
        },
        
        /**
         * checks if an item has been registered already
         * 
         * @param {String} contentType
         * @return {Bool}
         */
        has: function(contentType) {
            if (this.items.hasOwnProperty(contentType)) {
                return true;
            }
            
            return false;
        }
    }
}();
