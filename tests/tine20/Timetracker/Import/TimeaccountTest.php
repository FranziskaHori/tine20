<?php
/**
 * Tine 2.0 - http://www.tine20.org
 *
 * @package     Timetracker
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2018 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Christian Feitl<c.feitl@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

class Timetracker_Import_TimeaccountTest extends TestCase
{
    /**
     * @var Tinebase_Model_Container
     */
    protected $_importContainer = null;

    protected function tearDown()
    {
        
    }

    public function testImportDemoData()
    {
        if (Tinebase_DateTime::now()->setTimezone('UTC')->format('d') !== Tinebase_DateTime::now()->setTimezone(
                Tinebase_Core::getUserTimezone())->format('d')) {
            static::markTestSkipped('utc / usertimezone have a different date, test would fail');
        }

        $importer_timeaccount = new Tinebase_Setup_DemoData_Import('Timetracker_Model_Timeaccount', [
            'definition' => 'time_import_timeaccount_csv',
        ]);

        $importer_timeaccount->importDemodata();

        $filter_timeaccount = Tinebase_Model_Filter_FilterGroup::getFilterForModel('Timetracker_Model_Timeaccount', [
            ['field' => 'creation_time', 'operator' => 'within', 'value' => 'dayThis']
        ]);
        $result_timeraccount = Timetracker_Controller_Timeaccount::getInstance()->search($filter_timeaccount);

        self::assertEquals(4, count($result_timeraccount));

    }
}