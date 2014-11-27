<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Sales
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * 
 */

/**
 * Test class for Sales Invoice Controller
 */
class Sales_InvoiceControllerTests extends Sales_InvoiceTestCase
{
    protected $_testUser = NULL;
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Sales Invoice Controller Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
    }
    
    /**
     * (non-PHPdoc)
     * @see TestCase::tearDown()
     */
    protected function tearDown()
    {
        // switch back to admin user
        if ($this->_testUser) {
            Tinebase_Core::set(Tinebase_Core::USER, $this->_testUser);
        }
        
        parent::tearDown();
        
    }
    
    protected function _createFailingContracts()
    {
        // add contract not to bill
        $this->_contractRecords->addRecord($this->_contractController->create(new Sales_Model_Contract(array(
            'number'       => 5,
            'title'        => Tinebase_Record_Abstract::generateUID(),
            'description'  => '5 unittest no auto',
            'container_id' => $this->_sharedContractsContainerId,
            'billing_address_id' => $this->_addressRecords->filter('customer_id', $this->_customerRecords->filter('name', 'Customer3')->getFirstRecord()->getId())->filter('type', 'billing')->getFirstRecord()->getId(),
            'start_date' => $this->_referenceDate,
            'end_date' => NULL,
        ))));
        
        // add contract without customer
        $contract = new Sales_Model_Contract(array(
            'number'       => 6,
            'title'        => Tinebase_Record_Abstract::generateUID(),
            'description'  => '6 unittest auto not possible',
            'container_id' => $this->_sharedContractsContainerId,
            'start_date' => $this->_referenceDate,
            'end_date' => NULL,
            'billing_address_id' => $this->_addressRecords->filter('customer_id', $this->_customerRecords->filter('name', 'Customer3')->getFirstRecord()->getId())->filter('type', 'billing')->getFirstRecord()->getId(),
        ));
        
        $contract->relations = array(
            array(
                'own_model'              => 'Sales_Model_Contract',
                'own_backend'            => Tasks_Backend_Factory::SQL,
                'own_id'                 => NULL,
                'own_degree'             => Tinebase_Model_Relation::DEGREE_SIBLING,
                'related_model'          => 'Sales_Model_CostCenter',
                'related_backend'        => Tasks_Backend_Factory::SQL,
                'related_id'             => $this->_costcenterRecords->getFirstRecord()->getId(),
                'type'                   => 'LEAD_COST_CENTER'
            ),
        );
        
        $this->_contractRecords->addRecord($this->_contractController->create($contract));
        
        // add contract without address
        $contract = new Sales_Model_Contract(array(
            'number'       => 7,
            'title'        => Tinebase_Record_Abstract::generateUID(),
            'description'  => '7 unittest auto not possible',
            'container_id' => $this->_sharedContractsContainerId,
            'start_date' => $this->_referenceDate,
            'end_date' => NULL,
        ));
        
        $this->_contractRecords->addRecord($this->_contractController->create($contract));
    }
    
    /**
     * tests auto invoice creation
     */
    public function testFullAutoInvoice()
    {
        $this->markTestSkipped('0010492: fix failing invoices and timetracker tests');
        
        $this->_createFullFixtures();
        $this->_createFailingContracts();
        
        $this->assertEquals(7, $this->_contractRecords->count());
        
        $date = clone $this->_referenceDate;
        
        $i = 0;
        
        // the whole year, 12 months
        while ($i < 12) {
            $result = $this->_invoiceController->createAutoInvoices($date);
            $date->addMonth(1);
            $i++;
        }
        
        $this->assertEquals(6, count($result['failures']));
        
        $failures = '';
        foreach($result['failures'] as $failure) {
            $failures .= $failure;
        }
        
        $this->assertTrue(strstr($failures, 'no customer') !== FALSE);
        $this->assertTrue(strstr($failures, 'no billing') !== FALSE);
        $this->assertTrue(strstr($failures, 'no costcenter') !== FALSE);
        
        // also add an hour to get the last end
        $date->addHour(1);
        $this->_invoiceController->createAutoInvoices($date);
        $date->addHour(1);
        $this->_invoiceController->createAutoInvoices($date);
        
        $all = $this->_invoiceController->getAll();
        
        $cc1 = $this->_costcenterRecords->filter('remark', 'unittest1')->getFirstRecord();
        $cc2 = $this->_costcenterRecords->filter('remark', 'unittest2')->getFirstRecord();
        $cc3 = $this->_costcenterRecords->filter('remark', 'unittest3')->getFirstRecord();
        $cc4 = $this->_costcenterRecords->filter('remark', 'unittest4')->getFirstRecord();
        
        $all->setTimezone(Tinebase_Core::getUserTimezone());
        
        $customer1Invoices = $all->filter('costcenter_id', $cc1->getId())->sort('start_date');
        $customer2Invoices = $all->filter('costcenter_id', $cc2->getId())->sort('start_date');
        $customer3Invoices = $all->filter('costcenter_id', $cc3->getId())->sort('start_date');
        $customer4Invoices = $all->filter('costcenter_id', $cc4->getId())->sort('start_date');
        
        // customer 1 must have one invoice (timeaccount with budget has been billed the first month)
        $this->assertEquals(1, $customer1Invoices->count(), 'Customer 1 must have 1 invoice!');
        
        // customer 2 must have one invoice (timeaccount with budget has been billed the first time)
        $this->assertEquals(1, $customer2Invoices->count(), 'Customer 2 must have 1 invoice!');
        
        // there are timesheets in 2 intervals, so no empty invoice should be generated
        $this->assertEquals(2, $customer3Invoices->count(), 'Customer 3 must have 2 invoices!');
        
        // there are 2 products, interval 3,6 -> so every quarter in this year and the first of next year must be found
        $this->assertEquals(5, $customer4Invoices->count(), 'Customer 4 must have 5 invoices!');
        
        // test invoice positions
        $allInvoicePositions = Sales_Controller_InvoicePosition::getInstance()->getAll();

        $this->assertEquals(1, $allInvoicePositions->filter('invoice_id', $customer1Invoices->getFirstRecord()->getId())->count());
        $this->assertEquals(1, $allInvoicePositions->filter('invoice_id', $customer2Invoices->getFirstRecord()->getId())->count());
        
        // each invoice should contain 1 timeaccount
        foreach($customer3Invoices as $ci) {
            $this->assertEquals(1, $allInvoicePositions->filter('invoice_id', $ci->getId())->count());
        }
        
        // we need 9,3,9,3,9 invoice positions
        $i = 1;
        foreach($customer4Invoices as $ci) {
            $ip = $allInvoicePositions->filter('invoice_id', $ci->getId());
            $this->assertEquals(($i % 2 == 1) ? 9 : 3, $ip->count());
            $i++;
        }
        
        // contract 1 gets billed at the begin of the period
        $c1IArray = $customer1Invoices->start_date;
        
        $this->assertEquals($this->_referenceYear . '-01-01 00:00:00', $c1IArray[0]->toString());
        
        $c1IArray = $customer1Invoices->end_date;
        $this->assertEquals($this->_referenceYear . '-01-31 23:59:59', $c1IArray[0]->toString());
        
        // contract 2 gets billed at the end of the period, and the second period ends at 1.8.20xx
        $c2IsArray = $customer2Invoices->start_date;
        $c2IeArray = $customer2Invoices->end_date;
        
        $this->assertEquals($this->_referenceYear . '-05-01 00:00:00', $c2IsArray[0]->toString());
        $this->assertEquals($this->_referenceYear . '-05-31 23:59:59', $c2IeArray[0]->toString());
        
        // test correct timesheet handling of customer 3
        $c3IsArray = $customer3Invoices->start_date;
        $c3IeArray = $customer3Invoices->end_date;
        
        $this->assertEquals($this->_referenceYear . '-05-01 00:00:00', $c3IsArray[0]->toString());
        $this->assertEquals($this->_referenceYear . '-05-31 23:59:59', $c3IeArray[0]->toString());
        
        $this->assertEquals($this->_referenceYear . '-09-01 00:00:00', $c3IsArray[1]->toString());
        $this->assertEquals($this->_referenceYear . '-09-30 23:59:59', $c3IeArray[1]->toString());
        
        // test customer 4 having products only
        $c4IsArray = $customer4Invoices->start_date;
        $c4IeArray = $customer4Invoices->end_date;
        
        // should contain billeachquarter & billhalfyearly
        $this->assertEquals($this->_referenceYear . '-01-01 00:00:00', $c4IsArray[0]->toString());
        $this->assertEquals($this->_referenceYear . '-06-30 23:59:59', $c4IeArray[0]->toString());
        
        // should contain billeachquarter
        $this->assertEquals($this->_referenceYear . '-04-01 00:00:00', $c4IsArray[1]->toString());
        $this->assertEquals($this->_referenceYear . '-06-30 23:59:59', $c4IeArray[1]->toString());
        
        // should contain billeachquarter & billhalfyearly
        $this->assertEquals($this->_referenceYear . '-07-01 00:00:00', $c4IsArray[2]->toString());
        $this->assertEquals($this->_referenceYear . '-12-31 23:59:59', $c4IeArray[2]->toString());
        
        // should contain billeachquarter
        $this->assertEquals($this->_referenceYear . '-10-01 00:00:00', $c4IsArray[3]->toString());
        $this->assertEquals($this->_referenceYear . '-12-31 23:59:59', $c4IeArray[3]->toString());
        
        // look if hours of timesheets gets calculated properly
        $c3Invoice = $customer3Invoices->getFirstRecord();
        $filter = new Sales_Model_InvoicePositionFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $c3Invoice->getId())));
        $c3InvoicePositions = Sales_Controller_InvoicePosition::getInstance()->search($filter);
        
        $this->assertEquals(1, $c3InvoicePositions->count());
        $this->assertEquals(3.5, $c3InvoicePositions->getFirstRecord()->quantity);
        
        $invoice = $customer1Invoices->getFirstRecord();
        $invoice->relations = Tinebase_Relations::getInstance()->getRelations('Sales_Model_Invoice', 'Sql', $invoice->getId())->toArray();
        
        $filter = new Sales_Model_InvoicePositionFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $invoice['id'])));
        $invoice->positions = Sales_Controller_InvoicePosition::getInstance()->search($filter);
        
        $invoice->cleared = 'CLEARED';
        $invoice = $this->_invoiceController->update($invoice);
        
        // check correct number generation
        $this->assertEquals("R-00001", $invoice->number);
        
        $invoice = $customer2Invoices->getFirstRecord();
        $invoice->relations = Tinebase_Relations::getInstance()->getRelations('Sales_Model_Invoice', 'Sql', $invoice->getId())->toArray();
        $filter = new Sales_Model_InvoicePositionFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $invoice['id'])));
        $invoice->positions = Sales_Controller_InvoicePosition::getInstance()->search($filter);
        
        $invoice->cleared = 'CLEARED';
        $invoice = $this->_invoiceController->update($invoice);
        
        $this->assertEquals("R-00002", $invoice->number);
        
        // check disallow editing invoice after clearing
        $invoice->credit_term = 20;
        $this->setExpectedException('Sales_Exception_InvoiceAlreadyClearedEdit');
        
        $this->_invoiceController->update($invoice);
    }
    
    public function testDeleteInvoice()
    {
        $this->_createFullFixtures();
        
        $date = clone $this->_referenceDate;
        $i = 0;
        
        // the whole year, 12 months
        while ($i < 12) {
            $result = $this->_invoiceController->createAutoInvoices($date);
            $date->addMonth(1);
            $i++;
        }
        
        $paController = Sales_Controller_ProductAggregate::getInstance();
        $productAggregates = $paController->getAll();
        $contracts = $this->_contractController->getAll();
        $contracts->sort('id', 'DESC');
        
        $c1 = $contracts->getFirstRecord();
        
        $this->assertEquals(5, $productAggregates->count());
        
        $taController = Timetracker_Controller_Timeaccount::getInstance();
        $tsController = Timetracker_Controller_Timesheet::getInstance();
        
        $allTimesheets = $tsController->getAll();
        $allTimeaccounts = $taController->getAll();
        
        foreach($allTimesheets as $ts) {
            $this->assertTrue($ts->invoice_id != NULL);
        }
        
        foreach($allTimeaccounts as $ta) {
            if (intval($ta->budget) == 0) {
                $this->assertTrue($ta->invoice_id == NULL);
            }
        }

        $allInvoices = $this->_invoiceController->getAll('start_date', 'DESC');
        $this->assertEquals(8, $allInvoices->count(), print_r($allInvoices->toArray(), 1));
        
        foreach($allInvoices as $invoice) {
            $this->_invoiceController->delete($invoice);
        }
        
        $allTimesheets = $tsController->getAll();
        $allTimeaccounts = $taController->getAll();
        
        foreach($allTimeaccounts as $ta) {
            if (intval($ta->budget) == 0) {
                $this->assertTrue($ta->invoice_id == NULL, print_r($ta->toArray(), 1));
            }
        }
        
        foreach($allTimesheets as $ts) {
            $this->assertTrue($ts->invoice_id == NULL, print_r($ts->toArray(), 1));
        }
    }
    
    /**
     * @see: rt127444
     * 
     * make sure timeaccounts won't be billed if they shouldn't
     */
    public function testBudgetTimeaccountBilled()
    {
        $this->_createFullFixtures();
        
        $date = clone $this->_referenceDate;
        $i = 0;
        
        // do not set to bill, this ta has a budget
        $customer1Timeaccount = $this->_timeaccountRecords->filter('title', 'TA-for-Customer1')->getFirstRecord();
        $customer1Timeaccount->status = 'not yet billed';
        
        $tsController = Timetracker_Controller_Timesheet::getInstance();
        $taController = Timetracker_Controller_Timeaccount::getInstance();
        $taController->update($customer1Timeaccount);
        
        // this is a ts on 20xx-01-17
        $timesheet = new Timetracker_Model_Timesheet(array(
            'account_id' => Tinebase_Core::getUser()->getId(),
            'timeaccount_id' => $customer1Timeaccount->getId(),
            'start_date' => $date->addDay(17),
            'duration' => 120,
            'description' => 'ts from ' . (string) $date,
        ));
        
        $tsController->create($timesheet);
        
        // this is a ts on 20xx-02-03
        $timesheet->id = NULL;
        $timesheet->start_date  = $date->addDay(17);
        $timesheet->description = 'ts from ' . (string) $date;
        
        $tsController->create($timesheet);
        
        $date = clone $this->_referenceDate;
        $date->addMonth(1);
        
        $result = $this->_invoiceController->createAutoInvoices($date);
        
        $this->assertEquals(1, count($result['created']));
        
        $customer1Timeaccount->status = 'to bill';
        $taController->update($customer1Timeaccount);
        
        $date->addSecond(1);
        
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(1, count($result['created']));
        
        $invoiceId = $result['created'][0];
        $invoice = $this->_invoiceController->get($invoiceId);
        $found = FALSE;
        
        foreach($invoice->relations as $relation) {
            if ($relation->related_model == 'Timetracker_Model_Timeaccount') {
                $this->assertEquals('TA-for-Customer1',     $relation->related_record->title);
                $found = TRUE;
            }
        }

        $this->assertTrue($found, 'the timeaccount could not be found in the invoice!');
    }
    

    /**
     * tests if the rights work: Sales_Acl_Rights::SET_INVOICE_NUMBER, Sales_Acl_Rights::MANAGE_INVOICES
     */
    public function testSetManualNumberRight()
    {
        $this->_createCustomers();
        $this->_createCostCenters();
        
        $customer = $this->_customerRecords->filter('name', 'Customer1')->getFirstRecord();
        $invoice = $this->_invoiceController->create(new Sales_Model_Invoice(array(
            'number' => 'R-3000',
            'customer_id' => $customer->getId(),
            'description' => 'Manual',
            'address_id' => $this->_addressRecords->filter('customer_id', $customer->getId())->getFirstRecord()->getId(),
            'costcenter_id' => $this->_costcenterRecords->getFirstRecord()->getId()
        )));
        
        // fetch user group
        $group   = Tinebase_Group::getInstance()->getGroupByName('Users');
        $groupId = $group->getId();
        
        // create new user
        $user = new Tinebase_Model_FullUser(array(
            'accountLoginName'      => 'testuser',
            'accountPrimaryGroup'   => $groupId,
            'accountDisplayName'    => 'Test User',
            'accountLastName'       => 'User',
            'accountFirstName'      => 'Test',
            'accountFullName'       => 'Test User',
            'accountEmailAddress'   => 'unittestx8@tine20.org',
        ));
        
        $user = Admin_Controller_User::getInstance()->create($user, 'pw', 'pw');
        $this->_testUser = Tinebase_Core::getUser();

        Tinebase_Core::set(Tinebase_Core::USER, $user);
        
        $e = new Exception('No Message');
        
        try {
            $invoice = $this->_invoiceController->create(new Sales_Model_Invoice(array(
                'number' => 'R-3001',
                'customer_id' => $customer->getId(),
                'description' => 'Manual Forbidden',
                'address_id' => $this->_addressRecords->filter('customer_id', $customer->getId())->getFirstRecord()->getId(),
                'costcenter_id' => $this->_costcenterRecords->getFirstRecord()->getId()
            )));
        } catch (Exception $e) {
        }
        
        $this->assertTrue(get_class($e) == 'Tinebase_Exception_AccessDenied');
        $this->assertTrue($e->getMessage() == 'You don\'t have the right to manage invoices!');
        
        Tinebase_Core::set(Tinebase_Core::USER, $this->_testUser);
        
        $fe = new Admin_Frontend_Json();
        $userRoles = $fe->getRoles('user', array(), array(), 0, 1);
        $userRole = $fe->getRole($userRoles['results'][0]['id']);
        
        $roleRights = $fe->getRoleRights($userRole['id']);
        $roleMembers = $fe->getRoleMembers($userRole['id']);
        $roleMembers['results'][] = array('name' => 'testuser', 'type' => 'user', 'id' => $user->accountId);
        
        $app = Tinebase_Application::getInstance()->getApplicationByName('Sales');
        
        $roleRights['results'][] = array('application_id' => $app->getId(), 'right' => Sales_Acl_Rights::MANAGE_INVOICES);
        $fe->saveRole($userRole, $roleMembers['results'], $roleRights['results']);
        
        Tinebase_Core::set(Tinebase_Core::USER, $user);
        
        $e = new Exception('No Message');
        
        try {
            $invoice = $this->_invoiceController->create(new Sales_Model_Invoice(array(
                'number' => 'R-3001',
                'customer_id' => $customer->getId(),
                'description' => 'Manual Forbidden',
                'address_id' => $this->_addressRecords->filter('customer_id', $customer->getId())->getFirstRecord()->getId(),
                'costcenter_id' => $this->_costcenterRecords->getFirstRecord()->getId()
            )));
        } catch (Exception $e) {
        }
        
        $this->assertEquals('Tinebase_Exception_AccessDenied', get_class($e));
        $this->assertEquals('You have no right to set the invoice number!', $e->getMessage());
    }
    
    /**
     * tests if a product aggregate gets billed in the correct periods
     */
    public function testOneProductContractInterval()
    {
        $startDate = clone $this->_referenceDate;
        
        $this->_createProducts();
        
        $this->_createCustomers(1);
        $this->_createCostCenters();
        
        $monthBack = clone $this->_referenceDate;
        $monthBack->subMonth(1);
        $addressId = $this->_addressRecords->filter(
                'customer_id', $this->_customerRecords->filter(
                    'name', 'Customer1')->getFirstRecord()->getId())->filter(
                        'type', 'billing')->getFirstRecord()->getId();
        
        $this->assertTrue($addressId !== NULL);
        
        // this contract begins 6 months before the first invoice will be created
        $this->_createContracts(array(array(
            'number'       => 100,
            'title'        => 'MyContract',
            'description'  => 'unittest',
            'container_id' => $this->_sharedContractsContainerId,
            'billing_point' => 'begin',
            'billing_address_id' => $addressId,
            
            'interval' => 1,
            'start_date' => $startDate->subMonth(6),
            'last_autobill' => clone $this->_referenceDate,
            'end_date' => NULL,
            'products' => array(
                array('product_id' => $this->_productRecords->getByIndex(0)->getId(), 'quantity' => 1, 'interval' => 1, 'last_autobill' => $monthBack),
            )
        )));
        
        $startDate = clone $this->_referenceDate;
        // the whole year, 12 months
        $i=0;
        
        $startDate->addDay(5);
        
        while ($i < 24) {
            $startDate->addMonth(1);
            $result = $this->_invoiceController->createAutoInvoices($startDate);
            $this->assertEquals(1, $result['created_count']);
            $i++;
        }
        
        $invoices = $this->_invoiceController->getAll('start_date');
        $this->assertEquals('0101', $invoices->getFirstRecord()->start_date->format('md'));
        
        $this->assertEquals(24, $invoices->count());
        
        $filter = new Sales_Model_InvoicePositionFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'in', 'value' => $invoices->getArrayOfIds())));
        
        $pagination = new Tinebase_Model_Pagination(array('sort' => 'month', 'dir' => 'ASC'));
        
        $invoicePositions = Sales_Controller_InvoicePosition::getInstance()->search($filter, $pagination);
        
        // get sure each invoice positions has the same month as the invoice and the start_date is the first
        foreach($invoices as $invoice) {
            $month = (int) $invoice->start_date->format('n');
            $index = $month - 1;
            
            $this->assertEquals('01', $invoice->start_date->format('d'));
            $this->assertEquals($this->_lastMonthDays[$index], $invoice->end_date->format('d'), print_r($invoice->toArray(), 1));
            
            $this->assertEquals(1, $invoice->start_date->format('d'));
            
            $pos = $invoicePositions->filter('invoice_id', $invoice->getId())->getFirstRecord();
            $this->assertEquals($invoice->start_date->format('Y-m'), $pos->month);
            $this->assertEquals($invoice->end_date->format('Y-m'), $pos->month);
        }
        
        $this->assertEquals(24, $invoicePositions->count());
        
        $this->assertEquals($this->_referenceYear . '-01', $invoicePositions->getFirstRecord()->month);
        
        $invoicePositions->sort('month', 'DESC');
        
        $this->assertEquals($this->_referenceYear + 1 . '-12', $invoicePositions->getFirstRecord()->month);
    }
    
    /**
     * test product only contract setting last_autobill and resetting last_autobill on delete
     */
    public function testLastAutobillAfterDeleteInvoice()
    {
        $startDate = clone $this->_referenceDate;
        $lab = clone $this->_referenceDate;
        $lab->subMonth(1);
        $this->_createProducts(array(array(
            'name' => 'Hours',
            'description' => 'timesheets',
            'price' => '100',
            'accountable' => 'Timetracker_Model_Timeaccount'
        )));
        
        $this->_createCustomers(1);
        $this->_createCostCenters();
        
        // has budget, is to bill
        $ta = $this->_createTimeaccounts(array(array(
            'title'         => 'Tacss',
            'description'   => 'blabla',
            'is_open'       => 1,
            'status'        => 'open',
            'budget'        => NULL,
            
        )))->getFirstRecord();
        
        // has timeaccount without budget, must be billed at end of the period (each month has at least one timesheet)
        $this->_createContracts(array(array(
            'number'       => 100,
            'title'        => 'MyContract',
            'description'  => 'unittest',
            'container_id' => $this->_sharedContractsContainerId,
            'billing_address_id' => $this->_addressRecords->filter(
                'customer_id', $this->_customerRecords->filter(
                    'name', 'Customer1')->getFirstRecord()->getId())->filter(
                        'type', 'billing')->getFirstRecord()->getId(),
        
            'start_date' => $startDate,
            'last_autobill' => NULL,
            'end_date' => NULL,
            'products' => array(
                array('product_id' => $this->_productRecords->getByIndex(0)->getId(), 
                    'quantity' => 1, 'interval' => 1, 'billing_point' => 'end'),
            )
        )));
        
        // create timesheets
        $tsDate = clone $this->_referenceDate;
        $tsDate->addDay(10);
        
        $i = 0;
        while($i < 12) {
            $this->_createTimesheets(array(array(
                'account_id' => Tinebase_Core::getUser()->getId(),
                'timeaccount_id' => $ta->getId(),
                'start_date' => $tsDate,
                'duration' => 105,
                'description' => 'ts from ' . (string) $tsDate,
            )));
            $tsDate->addMonth(1);
            $i++;
        }
        
        
        $contract = $this->_contractController->getAll()->getFirstRecord();
        $this->assertEquals($startDate->__toString(), $contract->start_date->__toString());
        
        // find product aggregate
        $paController = Sales_Controller_ProductAggregate::getInstance();
        $productAggregate = $paController->getAll()->getFirstRecord();
        $productAggregate->setTimezone(Tinebase_Core::getUserTimezone());
        
        $this->assertEquals(NULL, $productAggregate->last_autobill);
        
        // create 6 invoices - each month one invoice - last autobill must be increased each month
        for ($i = 1; $i < 7; $i++) {
            $myDate = clone $this->_referenceDate;
            $myDate->addMonth($i)->addHour(3);
            
            $testDate = clone $this->_referenceDate;
            $testDate->addMonth($i);
            
            $result = $this->_invoiceController->createAutoInvoices($myDate);
            $this->assertEquals(1, $result['created_count']);
            
            $productAggregate = $paController->get($productAggregate->getId());
            $productAggregate->setTimezone(Tinebase_Core::getUserTimezone());
            $this->assertEquals($testDate, $productAggregate->last_autobill);
        }
        
        $testDate = clone $this->_referenceDate;
        $testDate->addMonth(6);
        $this->assertEquals($testDate, $productAggregate->last_autobill);
        
        // delete all created invoices again
        $allInvoices = $this->_invoiceController->getAll('start_date', 'DESC');
        
        foreach($allInvoices as $invoice) {
            $this->_invoiceController->delete($invoice);
        }
        
        $testDate = clone $this->_referenceDate;
        
        $productAggregate = $paController->get($productAggregate->getId());
        $productAggregate->setTimezone(Tinebase_Core::getUserTimezone());
        
        $this->assertEquals(NULL, $productAggregate->last_autobill);
        
        // create 6 invoices again - each month one invoice - last autobill must be increased each month
        for ($i = 1; $i < 7; $i++) {
            $myDate = clone $this->_referenceDate;
            $myDate->addMonth($i)->addHour(3);
        
            $testDate = clone $this->_referenceDate;
            $testDate->addMonth($i);
        
            $result = $this->_invoiceController->createAutoInvoices($myDate);
            $this->assertEquals(1, $result['created_count']);
        
            $productAggregate = $paController->get($productAggregate->getId());
            $productAggregate->setTimezone(Tinebase_Core::getUserTimezone());
            $this->assertEquals($testDate, $productAggregate->last_autobill);
        }
        
        $testDate = clone $this->_referenceDate;
        $testDate->addMonth(6);
        $this->assertEquals($testDate, $productAggregate->last_autobill);
        
    }
    
    /**
     * @see step2 FS0012
     * 
     * products must be billed on the beginning of a period
     * the first dates must fit
     */
    public function testProductWithOneYearInterval()
    {
        // last year, the 1.1 in usertimezone
        $date      = clone $this->_referenceDate;
        
        $startDateContract = clone $this->_referenceDate;
        $startDateContract->subMonth(13);
        
        // this has been billed for the last year.
        $startDateProduct = clone $this->_referenceDate;
        $startDateProduct->subMonth(12);
        
        $this->_createProducts();
        
        $this->_createCustomers(1);
        $this->_createCostCenters();
        
        $addressId = $this->_addressRecords->filter(
                'customer_id', $this->_customerRecords->filter(
                    'name', 'Customer1')->getFirstRecord()->getId())->filter(
                        'type', 'billing')->getFirstRecord()->getId();
        
        // the contract has an interval of 0, but it has to be billed
        $this->_createContracts(array(array(
            'number'       => 100,
            'title'        => 'MyContract',
            'description'  => 'unittest',
            'container_id' => $this->_sharedContractsContainerId,
            'billing_point' => 'begin',
            'billing_address_id' => $addressId,
            'interval' => 0,
            'start_date' => $startDateContract,
            'last_autobill' => clone $this->_referenceDate,
            'end_date' => NULL,
            'products' => array(
                array('product_id' => $this->_productRecords->getByIndex(0)->getId(),
                    'quantity' => 1, 'interval' => 12, 'last_autobill' => $startDateProduct),
            )
        )));
        
        $startDate = clone $this->_referenceDate;
        $startDate->addHour(3);
        
        $i=0;
        
        $result = $this->_invoiceController->createAutoInvoices($startDate);
        $this->assertEquals(1, $result['created_count']);
        
        $invoice = $this->_invoiceController->get($result['created'][0]);
        
        $invoicePositions = Sales_Controller_InvoicePosition::getInstance()->getAll('month')->filter('invoice_id', $result['created'][0]);
        $this->assertEquals(12, $invoicePositions->count());
        
        $i = 1;
        
        foreach($invoicePositions as $ipo) {
            $this->assertEquals($this->_referenceYear . '-' . ($i > 9 ? '' : '0') . $i, $ipo->month, print_r($invoicePositions->toArray(), 1));
            $this->assertEquals($ipo->invoice_id, $invoice->getId());
            $i++;
        }
    }
    
    /**
     * make sure that timesheets get created for the right month
     */
    public function testTimesheetOnMonthEndAndBegin()
    {
        $dates = array(
            clone $this->_referenceDate,
            clone $this->_referenceDate,
            clone $this->_referenceDate,
            clone $this->_referenceDate
        );
        // 0: 1.1.xxxx, 1: 31.1.xxxx, 2: 1.2.xxxx, 3: 28/29.2.xxxx
        $dates[1]->addMonth(1)->subDay(1);
        $dates[2]->addMonth(1);
        $dates[3]->addMonth(2)->subDay(1);
        
        $customer = $this->_createCustomers(1)->getFirstRecord();
        $this->_createCostCenters();
        
        // has no budget
        $ta = $this->_createTimeaccounts(array(array(
            'title'         => 'TaTest',
            'description'   => 'blabla',
            'is_open'       => 1,
            'status'        => 'not yet billed',
            'budget'        => null
        )))->getFirstRecord();
        
        foreach($dates as $date) {
            $this->_createTimesheets(array(
                array(
                    'account_id' => Tinebase_Core::getUser()->getId(),
                    'timeaccount_id' => $ta->getId(),
                    'start_date' => $date,
                    'duration' => 105,
                    'description' => 'ts from ' . (string) $date,
                ))
            );
        }
        
        $this->assertEquals(4, $this->_timesheetRecords->count());
        
        $csDate = clone $this->_referenceDate;
        $csDate->subMonth(10);
        
        $lab = clone $this->_referenceDate;
        // set start position (must be manually set on introducing the invoice module)
        $lab->subMonth(1);
        $this->_createProducts();
        $this->_createContracts(array(array(
            'number'       => 100,
            'title'        => 'MyContract',
            'description'  => 'unittest',
            'container_id' => $this->_sharedContractsContainerId,
            'billing_point' => 'begin',
            'billing_address_id' => $this->_addressRecords->filter(
                'customer_id', $customer->getId())->filter(
                        'type', 'billing')->getFirstRecord()->getId(),
        
            'start_date' => $csDate,
            'end_date' => NULL,
            'products' => array(
                    array('start_date' => $csDate, 'end_date' => NULL, 'quantity' => 1, 'interval' => 1, 'billing_point' => 'end', 'product_id' => $this->_productRecords->filter('name', 'Hours')->getFirstRecord()->getId()),
            )
        )));
        
        $json = new Sales_Frontend_Json();

        $date = clone $this->_referenceDate;
        // this is set by cli if called by cli
        $date->setTime(3,0,0);
        
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(0, $result['created_count'], (string) $date);
        sleep(1);
        $date->addMonth(1);
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(1, $result['created_count'], (string) $date);
        $invoice1Id = $result['created'][0];
        $invoice = $json->getInvoice($invoice1Id);
        $this->assertEquals(1, count($invoice['positions']), print_r($invoice['positions'], 1));
        sleep(1);
        $date->addMonth(1);
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(1, $result['created_count'], (string) $date);
        $invoice2Id = $result['created'][0];
        $invoice = $json->getInvoice($invoice2Id);
        $this->assertEquals(1, count($invoice['positions']));
        sleep(1);
        $date->addMonth(1);
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(0, $result['created_count'], (string) $date);
        
        $filter = new Timetracker_Model_TimesheetFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $invoice1Id)));
        $timesheets = $this->_timesheetController->search($filter);
        $this->assertEquals(2, $timesheets->count());
        
        $filter = new Timetracker_Model_TimesheetFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $invoice2Id)));
        $timesheets = $this->_timesheetController->search($filter);
        $this->assertEquals(2, $timesheets->count());
        
        // now try to delete the first invoice, which is not allowed
        $this->setExpectedException('Sales_Exception_DeletePreviousInvoice');
        
        $this->_invoiceController->delete(array($invoice1Id));
    }
    
    /**
     * make sure that timesheets get created for the right month
     */
    public function test2MonthIntervalTimesheetOnMonthEndAndBegin()
    {
        $dates = array(
            clone $this->_referenceDate,
            clone $this->_referenceDate,
            clone $this->_referenceDate,
            clone $this->_referenceDate
        );
        // 0: 1.1.xxxx, 1: 31.1.xxxx, 2: 1.2.xxxx, 3: 28/29.2.xxxx
        $dates[1]->addMonth(1)->subDay(1);
        $dates[2]->addMonth(1);
        $dates[3]->addMonth(2)->subDay(1);
    
        // create much more timesheets
        $dt = clone $this->_referenceDate;
        for ($i = 0; $i < 80; $i++) {
            $dt->addHour(12);
            $dates[] = clone $dt;
        }

        $customer = $this->_createCustomers(1)->getFirstRecord();
        $this->_createCostCenters();
    
        // has no budget
        $ta = $this->_createTimeaccounts(array(array(
            'title'         => 'TaTest',
            'description'   => 'blabla',
            'is_open'       => 1,
            'status'        => 'not yet billed',
            'budget'        => null
        )))->getFirstRecord();
    
        foreach($dates as $date) {
            $this->_createTimesheets(array(
                array(
                    'account_id' => Tinebase_Core::getUser()->getId(),
                    'timeaccount_id' => $ta->getId(),
                    'start_date' => $date,
                    'duration' => 105,
                    'description' => 'ts from ' . (string) $date,
                ))
            );
        }
    
        $this->assertEquals(84, $this->_timesheetRecords->count());
        $this->_createProducts();
        $csDate = clone $this->_referenceDate;
        $csDate->subMonth(10);
    
        $lab = clone $this->_referenceDate;
        $this->_createContracts(array(array(
            'number'       => 100,
            'title'        => 'MyContract',
            'description'  => 'unittest',
            'container_id' => $this->_sharedContractsContainerId,
            'billing_point' => 'begin',
            'billing_address_id' => $this->_addressRecords->filter(
                'customer_id', $customer->getId())->filter(
                    'type', 'billing')->getFirstRecord()->getId(),
    
            'start_date' => $csDate,
            'end_date' => NULL,
            'products' => array(
                array('start_date' => $csDate, 'end_date' => NULL, 'quantity' => 1, 'interval' => 2, 'billing_point' => 'end', 'product_id' => $this->_productRecords->filter('name', 'Hours')->getFirstRecord()->getId())
            )
        )));
    
        $json = new Sales_Frontend_Json();
    
        $date = clone $this->_referenceDate;
        // this is set by cli if called by cli
        $date->setTime(3,0,0);
    
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(0, $result['created_count']);
    
        $date->addMonth(1);
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(1, $result['created_count']);
        
        $invoice1Id = $result['created'][0];
        $filter = new Timetracker_Model_TimesheetFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $invoice1Id)));
        $timesheets = $this->_timesheetController->search($filter);
        $this->assertEquals(63, $timesheets->count());
        
        $date->addMonth(1);
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(1, $result['created_count'], (string) $date);
        $invoice2Id = $result['created'][0];
        $invoice = $json->getInvoice($invoice2Id);
        $this->assertEquals(1, count($invoice['positions']));
    
        $date->addMonth(1);
        $result = $this->_invoiceController->createAutoInvoices($date);
        $this->assertEquals(0, $result['created_count']);
    
        $filter = new Timetracker_Model_TimesheetFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $invoice2Id)));
        $timesheets = $this->_timesheetController->search($filter);
        $this->assertEquals(21, $timesheets->count());
    }
    
    /**
     * tests new fields
     */
    public function testManualInvoice()
    {
        $customer = $this->_createCustomers(1)->getFirstRecord();
        $this->_createCostCenters();
        
        $invoice = $this->_invoiceController->create(new Sales_Model_Invoice(array(
            'number' => 100,
            'description' => 'test',
            'address_id' => $this->_addressRecords->getFirstRecord()->getId(),
            'costcenter_id' => $this->_costcenterRecords->getFirstRecord()->getId(),
            'is_auto' => TRUE,
            'price_net' => 200.20,
            'price_gross' => 238.45,
            'sales_tax' => 19.5
        )));
        
        $this->assertEquals(19.5, $invoice->sales_tax);
        $this->assertEquals(200.20, $invoice->price_net);
        $this->assertEquals(238.45, $invoice->price_gross);
    }
    
    /**
     * tests if timesheets get resetted properly after deleting the invoice
     * and recreate the same invoice again containing the same timesheets
     */
    public function testDeleteAndRunAgainInvoice()
    {
        $this->_createFullFixtures();
    
        $date = clone $this->_referenceDate;
        $date->addMonth(8);
        $i = 0;
    
        $result = $this->_invoiceController->createAutoInvoices($date);
    
        $this->assertEquals(3, count($result['created']));
        
        $tsController = Timetracker_Controller_Timesheet::getInstance();
    
        // get first valid invoice id from all timesheets
        $tsInvoiceIds = array_unique($tsController->getAll()->invoice_id);
        sort($tsInvoiceIds);
        $tsInvoiceIds = array_reverse($tsInvoiceIds);
        $this->assertTrue(! empty($tsInvoiceIds[0]));
        $myInvoice = $this->_invoiceController->get($tsInvoiceIds[0]);

        $f = new Timetracker_Model_TimesheetFilter(array());
        $f->addFilter(new Tinebase_Model_Filter_Text(
                array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $myInvoice->getId())
        ));
        $myTimesheets = $tsController->search($f);
        $this->assertEquals(2, $myTimesheets->count(), 'timesheets not found for invoice ' . $myInvoice->getId());
        
        $this->_invoiceController->delete(array($myInvoice->getId()));
        $allTimesheets = $tsController->getAll();
        foreach($allTimesheets as $ts) {
            $this->assertSame(NULL, $ts->invoice_id, 'invoice id should be reset');
        }
        
        $this->_invoiceController->createAutoInvoices($date);
        
        $tsId = $myTimesheets->getFirstRecord()->getId();
        
        $myTimesheet = $tsController->get($tsId);
        $f = new Timetracker_Model_TimesheetFilter(array());
        $f->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $myTimesheet->invoice_id)));
        
        $myTimesheets = $tsController->search($f);
        $this->assertEquals(2, $myTimesheets->count());
        
        foreach($myTimesheets as $ts) {
            $this->assertEquals(40, strlen($ts->invoice_id));
        }
    }
    
    public function testInterval12LastAutobill()
    {
        $startDate = clone $this->_referenceDate;
        $startDate->subYear(1);
        
        $this->_createProducts(array(
                array('name' => 'bill yearly',
                'description' => 'bill every year',
                'price' => '1002','accountable' => 'Sales_Model_Product')
        ));
        $this->_createCustomers(1);
        $this->_createCostCenters();
        $addressId = $this->_addressRecords->filter(
                        'customer_id', $this->_customerRecords->filter(
                                'name', 'Customer1')->getFirstRecord()->getId())->filter(
                                        'type', 'billing')->getFirstRecord()->getId();
        
        // this contract begins 6 months before the first invoice will be created
        $this->_createContracts(array(array(
                'number'       => 100,
                'title'        => 'MyContract',
                'description'  => 'unittest',
                'container_id' => $this->_sharedContractsContainerId,
                'billing_point' => 'begin',
                'billing_address_id' => $addressId,
                'interval' => 12,
                'start_date' => $startDate,
                'last_autobill' => $startDate,
                'end_date' => NULL,
                'products' => array(
                        array('product_id' => $this->_productRecords->getByIndex(0)->getId(), 'quantity' => 1, 'interval' => 12, 'last_autobill' => $startDate),
                )
        )));
        
        $startDate = clone $this->_referenceDate;
        $startDate->subMonth(1);
        
        $startDate = clone $this->_referenceDate;
        $startDate->addDay(5);
        $result = $this->_invoiceController->createAutoInvoices($startDate);
        
        $this->assertEquals(1, $result['created_count']);
        
        $invoices = $this->_invoiceController->getAll();
        $firstInvoice = $invoices->getFirstRecord();
        $this->assertEquals(1, $invoices->count());
        
        $filter = new Sales_Model_InvoicePositionFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'in', 'value' => $invoices->getArrayOfIds())));
        
        $invoicePositions = Sales_Controller_InvoicePosition::getInstance()->search($filter);
        
        $this->assertEquals(12, $invoicePositions->count());
        
        $contract = $this->_contractRecords->getFirstRecord();
        $contract->setTimezone(Tinebase_Core::getUserTimezone());
        
        $autobillDate = clone $this->_referenceDate;
        
        for ($i = 0; $i < 8; $i++) {
            $startDate->addDay(1);
            $result = $this->_invoiceController->createAutoInvoices($startDate);
            $this->assertEquals(0, $result['created_count']);
        }
        
        $productAggregate = Sales_Controller_ProductAggregate::getInstance()->getAll()->getFirstRecord();
        $productAggregate->setTimezone(Tinebase_Core::getUserTimezone());
        $this->assertEquals($autobillDate, $productAggregate->last_autobill);
    }
    
    /**
     * tests if uncleared invoices gets deleted
     */
    public function testUnclearedDeletion()
    {
        $this->_createFullFixtures();
    
        $date = clone $this->_referenceDate;
        $date->addMonth(8);
        $i = 0;
    
        $result = $this->_invoiceController->createAutoInvoices($date);
    
        $this->assertEquals(3, count($result['created']));
        
        $invoice = $this->_invoiceController->get($result['created'][0]);
        $invoice->cleared = 'CLEARED';
        $this->_invoiceController->update($invoice);
        
        $cli = new Sales_Frontend_Cli();
        $cli->removeUnbilledAutoInvoices();
        
        $invoices = $this->_invoiceController->getAll();
        
        $this->assertEquals(1, $invoices->count());
    }
    
    /**
     * if no productaggregates are defined for a contract, but 
     * accountables are related, use default billing Info from accountable
     * (product will be created if it does not exist - is needed in the invoice position)
     */
    public function testDefaultAutobillInterval()
    {
        $startDate = clone $this->_referenceDate;
        $startDate->subYear(1);
        
        $this->_createCustomers(1);
        $this->_createCostCenters();
    
        $this->_createTimeaccounts(array(array(
                'title'         => 'TA',
                'description'   => 'blabla',
                'is_open'       => 1,
                'status'        => 'to bill',
                'budget'        => 100
        )));
        
        $addressId = $this->_addressRecords->filter(
                        'customer_id', $this->_customerRecords->filter(
                                'name', 'Customer1')->getFirstRecord()->getId())->filter(
                                        'type', 'billing')->getFirstRecord()->getId();
        
        // this contract begins 6 months before the first invoice will be created
        $this->_createContracts(array(array(
                'number'       => 100,
                'title'        => 'MyContract',
                'description'  => 'unittest',
                'container_id' => $this->_sharedContractsContainerId,
                'billing_point' => 'begin',
                'billing_address_id' => $addressId,
    
                'start_date' => $startDate,
                'end_date' => NULL,
        )));
    
        $startDate = clone $this->_referenceDate;
        $startDate->subMonth(1);
    
        $startDate = clone $this->_referenceDate;
        $startDate->addDay(5);
        $result = $this->_invoiceController->createAutoInvoices($startDate);
    
        $this->assertEquals(1, $result['created_count']);
        
        $filter = new Sales_Model_ProductFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'accountable', 'operator' => 'equals', 'value' => 'Timetracker_Model_Timeaccount')));
        
        $products = Sales_Controller_Product::getInstance()->search($filter);
        $this->assertEquals(1, $products->count());
        
        
        $this->assertEquals('Timetracker_Model_Timeaccount', $products->getFirstRecord()->accountable);
        
        $filter = new Sales_Model_InvoicePositionFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'invoice_id', 'operator' => 'equals', 'value' => $result['created'][0])));
        
        $invoicePositions = Sales_Controller_InvoicePosition::getInstance()->search($filter);
        
        $this->assertEquals(1, $invoicePositions->count());
    }
}