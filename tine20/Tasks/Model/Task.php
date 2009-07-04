<?php
/**
 * Tine 2.0
 * 
 * @package     Tasks
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 *
 */

/**
 * Task-Record Class
 * @package Tasks
 */
class Tasks_Model_Task extends Tinebase_Record_Abstract
{
	/**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tasks';
    
    /**
     * validators
     *
     * @var array
     */
    protected $_validators = array(
        // tine record fields
        'container_id'         => array('allowEmpty' => true,  'Int' ),
        'created_by'           => array('allowEmpty' => true,        ),
        'creation_time'        => array('allowEmpty' => true         ),
        'last_modified_by'     => array('allowEmpty' => true         ),
        'last_modified_time'   => array('allowEmpty' => true         ),
        'is_deleted'           => array('allowEmpty' => true         ),
        'deleted_time'         => array('allowEmpty' => true         ),
        'deleted_by'           => array('allowEmpty' => true         ),
        // task only fields
        'id'                   => array('allowEmpty' => true, 'Alnum'),
        'percent'              => array('allowEmpty' => true, 'default' => 0),
        'completed'            => array('allowEmpty' => true         ),
        'due'                  => array('allowEmpty' => true         ),
        // ical common fields
        'class_id'             => array('allowEmpty' => true, 'Int'  ),
        'description'          => array('allowEmpty' => true         ),
        'geo'                  => array('allowEmpty' => true, Zend_Filter_Input::DEFAULT_VALUE => NULL),
        'location'             => array('allowEmpty' => true         ),
        'organizer'            => array('allowEmpty' => true,        ),
        'priority'             => array('allowEmpty' => true, 'default' => 1),
        'status_id'            => array('allowEmpty' => true         ),
        'summary'              => array('presence' => 'required'     ),
        'url'                  => array('allowEmpty' => true         ),
        // ical common fields with multiple appearance
        'attach'                => array('allowEmpty' => true        ),
        'attendee'              => array('allowEmpty' => true        ),
        'tags'                  => array('allowEmpty' => true        ), //originally categories
        'comment'               => array('allowEmpty' => true        ),
        'contact'               => array('allowEmpty' => true        ),
        'related'               => array('allowEmpty' => true        ),
        'resources'             => array('allowEmpty' => true        ),
        'rstatus'               => array('allowEmpty' => true        ),
        // scheduleable interface fields
        'dtstart'               => array('allowEmpty' => true        ),
        'duration'              => array('allowEmpty' => true        ),
        'recurid'               => array('allowEmpty' => true        ),
        // scheduleable interface fields with multiple appearance
        'exdate'                => array('allowEmpty' => true        ),
        'exrule'                => array('allowEmpty' => true        ),
        'rdate'                 => array('allowEmpty' => true        ),
        'rrule'                 => array('allowEmpty' => true        ),
        // tine 2.0 notes
        'notes'                 => array('allowEmpty' => true        ),
    );
    
    /**
     * datetime fields
     *
     * @var array
     */
    protected $_datetimeFields = array(
        'creation_time', 
        'last_modified_time', 
        'deleted_time', 
        'completed', 
        'dtstart', 
        'due', 
        'exdate', 
        'rdate'
    );
    
    /**
     * the constructor
     * it is needed because we have more validation fields in Tasks
     * 
     * @param mixed $_data
     * @param bool $bypassFilters sets {@see this->bypassFilters}
     * @param bool $convertDates sets {@see $this->convertDates}
     */
    public function __construct($_data = NULL, $_bypassFilters = false, $_convertDates = true)
    {
        $this->_filters['organizer'] = new Zend_Filter_Empty(NULL);
        
        parent::__construct($_data, $_bypassFilters, $_convertDates);
    }
    
    /**
     * fill record from json data
     *
     * @param string $_data json encoded data
     * @return void
     */
    public function setFromJson($_data)
    {
        $data = Zend_Json::decode($_data);
        //Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($data, true));
        
        if (empty($data['geo'])) {
            $data['geo'] = NULL;
        }
        
        if (isset($data['container_id']) && is_array($data['container_id'])) {
            $data['container_id'] = $data['container_id']['id'];
        }
        
        if (isset($data['organizer']) && is_array($data['organizer'])) {
            $data['organizer'] = $data['organizer']['accountId'];
        }
        
        //Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($data, true));
        $this->setFromArray($data);
    }
}