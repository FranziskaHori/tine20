<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Setup
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Matthias Greiling <m.greiling@metaways.de>
 */


class Setup_Backend_Schema_Table_Mysql extends Setup_Backend_Schema_Table_Abstract
{

    public function __construct($_tableDefinition)
    {
         $this->setName($_tableDefinition->TABLE_NAME);
         if ($this->getBackend()->getDb()->getConfig()['charset'] === 'utf8') {
             $this->charset = 'utf8';
         } else {
             $this->charset = 'utf8mb4';
         }
    }
      
    public function setFields($_fieldDefinitions)
    {
        foreach ($_fieldDefinitions as $fieldDefinition) {
            $this->addField(Setup_Backend_Schema_Field_Factory::factory('Mysql', $fieldDefinition));
        }
    }
}
