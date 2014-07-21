/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * @copyright   Copyright (c) 2012 Metaways Infosystems GmbH (http://www.metaways.de)
 */
Ext.ns('Tine.HumanResources');

/**
 * @namespace   Tine.HumanResources
 * @class       Tine.HumanResources.FreeTimeEditDialog
 * @extends     Tine.widgets.dialog.EditDialog
 * 
 * <p>DatePicker with multiple days</p>
 * <p></p>
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * Create a new Tine.HumanResources.DatePicker
 */
Tine.HumanResources.DatePicker = Ext.extend(Ext.DatePicker, {
    
    recordClass: null,
    app: null,
    
    /**
     * the employee to use for this freetime
     * 
     * @type {Tine.HumanResources.Model.Employee}
     */
    employee: null,
    
    /**
     * dates higlighted as vacation day
     * 
     * @type {Array}
     */
    vacationDates: null,
    
    /**
     * dates higlighted as feast day
     * 
     * @type {Array}
     */
    feastDates : null,
    
    /**
     * dates higlighted as sickness day
     * 
     * @type {Array}
     */
    sicknessDates: null,
    
    /**
     * holds the freetime type (SICKNESS or VACATION)
     * 
     * @type {String}
     */
    freetimeType: null,
    
    /**
     * the editdialog this is nested in
     * 
     * @type {Tine.HumanResources.FreeTimeEditDialog}
     */
    editDialog: null,
    
    /**
     * if vacation is handled, the account picker of the edit dialog is active
     * 
     * @type {Boolean}
     */
    accountPickerActive: null,
    
    dateProperty: 'date',
    recordsProperty: 'freedays',
    foreignIdProperty: 'freetime_id',
    useWeekPickerPlugin: false,
    
    /**
     * holds the previous year selected (to switch back on no account found exception
     * 
     * @type {Number}
     */
    previousYear: null,
    
    /**
     * holds the current year selected
     * 
     * @type {Number}
     */
    currentYear: null,
    
    /**
     * initializes the component
     */
    initComponent: function() {
        if (this.useWeekPickerPlugin) {
            this.plugins = this.plugins ? this.plugins : [];
            this.plugins.push(new Ext.ux.DatePickerWeekPlugin({
                weekHeaderString: Tine.Tinebase.appMgr.get('Calendar').i18n._('WK')
            }));
        }
        
        this.vacationDates = [];
        this.sicknessDates = [];
        this.feastDates    = [];
        
        this.initStore();
        
        Tine.HumanResources.DatePicker.superclass.initComponent.call(this);
    },
    
    /**
     * initializes the store
     */
    initStore: function() {
        var picker = this;
        this.store = new Tine.Tinebase.data.RecordStore({
            remoteSort: false,
            recordClass: this.recordClass,
            autoSave: false,
            getByDate: function(date) {
                if (!Ext.isDate(date)) {
                    date = new Date(date);
                }
                var index = this.findBy(function(record) {
                    if(record.get(picker.dateProperty).toString() == date.toString()) {
                        return true;
                    }
                });
                return this.getAt(index);
            },
            getFirstDay: function() {
                this.sort('date', 'ASC');
                return this.getAt(0);
            },
            
            getLastDay: function() {
                this.sort('date', 'ASC');
                return this.getAt(this.getCount() - 1);
            }
        }, this);
    },
    
    /**
     * loads the feast days of the configured feast calendar from the server
     * 
     * @param {Boolean} fromYearChange
     * @param {Boolean} onInit
     * @param {Date} date
     */
    loadFeastDays: function(fromYearChange, onInit, date) {
        
        this.disableYearChange = fromYearChange;
        
        var employeeId = this.editDialog.fixedFields.get('employee_id').id;
        var year       = this.currentYear;
        var freeTimeId = this.editDialog.record.get('id') ? this.editDialog.record.get('id') : null;
        
        this.loadMask = new Ext.LoadMask(this.getEl(), {
            msg: this.app.i18n._('Loading calendar data...')
        });
        
        this.loadMask.show();
        
        var that = this;
        
        var req = Ext.Ajax.request({
            url : 'index.php',
            params : { 
                method:      'HumanResources.getFeastAndFreeDays', 
                _employeeId: employeeId, 
                _year:       year, 
                _freeTimeId: freeTimeId
            },
            success : function(_result, _request) {
                that.onFeastDaysLoad(Ext.decode(_result.responseText), onInit, date);
            },
            failure : function(exception) {
                Tine.Tinebase.ExceptionHandler.handleRequestException(exception, that.onFeastDaysLoadFailureCallback, that);
            },
            scope: that
        });
    },
    
    /**
     * loads the feast days from loadFeastDays
     * 
     * @param {Object} result
     * @param {Boolean} onInit
     * @param {Date} date
     */
    onFeastDaysLoad: function(result, onInit, date) {
        // wait until the accountpicker has found the current account
        if (this.accountPickerActive) {
            if (! (this.editDialog && this.editDialog.currentAccount)) {
                this.onFeastDaysLoad.defer(100, this, [result, onInit, date]);
                return;
            }
        }
        Tine.log.debug('Loaded feast and freedays:');
        var rr = result.results;
        Tine.log.debug(rr);
        Tine.log.debug(result);
        
        this.disabledDates = [];
        //  days not to work on by contract
        var exdates = rr.excludeDates || [];
        var freetime = this.editDialog.record;
        
        // format dates to fit the datepicker format
        Ext.each(exdates, function(d) {
            Ext.each(d, function(date) {
                var split = date.date.split(' '), dateSplit = split[0].split('-');
                var date = new Date(dateSplit[0], dateSplit[1] - 1, dateSplit[2]);
                this.disabledDates.push(date);
            }, this);
        }, this);

        this.setVacationDates(this.editDialog.localVacationDays);
        this.setSicknessDates(this.editDialog.localSicknessDays);
        this.setFeastDates(rr.feastDays);
        
        this.setDisabledDates(this.disabledDates);
        
        this.updateCellClasses();
        
        var split = rr.firstDay.date.split(' '), dateSplit = split[0].split('-');
        var firstDay = new Date(dateSplit[0], dateSplit[1] - 1, dateSplit[2]);
        this.setMinDate(firstDay);
        
        var split = rr.lastDay.date.split(' '), dateSplit = split[0].split('-');
        var lastDay = new Date(dateSplit[0], dateSplit[1] - 1, dateSplit[2]);
        this.setMaxDate(lastDay);
        
        // if ownFreeDays is empty, the record hasn't been saved already, so use the properties from the local record
        var iterate = (rr.ownFreeDays && rr.ownFreeDays.length > 0) ? rr.ownFreeDays : (freetime ? freetime.get('freedays') : null);
        
        if (Ext.isArray(iterate)) {
            Ext.each(iterate, function(fd) {
                var split = fd.date.split(' '), dateSplit = split[0].split('-');
                fd.date = new Date(dateSplit[0], dateSplit[1] - 1, dateSplit[2]);
                fd.date.clearTime();
                this.store.add(new this.recordClass(fd));
            }, this);
        }
        
        if (this.accountPickerActive) {
            var substractDays = this.editDialog.getDaysToSubstract();

            this.editDialog.getForm().findField('remaining_vacation_days').setValue(rr.allVacation - substractDays);
        }
        
        this.updateCellClasses();
        this.loadMask.hide();
        
        if (date) {
            var focusDate = date;
        } else if (this.disableYearChange) {
            if (this.previousYear < this.currentYear) {
                var focusDate = new Date(this.currentYear + '/01/01 12:00:00 AM');
            } else {
                var focusDate = new Date(this.currentYear + '/12/31 12:00:00 AM');
            }
        } else {
            var focusDate = freetime.get('firstday_date');
        }
        
        this.currentYear = parseInt(rr.firstDay.date.split('-')[0]);
        
        // focus
        if (focusDate) {
            this.update(focusDate);
        }
        
        this.enable();
        
        this.disableYearChange = false;
    },
    
    /**
     * if loading feast and freedays failes
     */
    onFeastDaysLoadFailureCallback: function() {
        var year = this.currentYear;
        this.currentYear = this.previousYear;
        this.previousYear = year;
        this.onYearChange();
    },
    
    /**
     * set vacation dates
     * 
     * @param {Object} localVacationDays
     * @param {Array} remoteVacationDays
     * @param {Object} locallyRemovedDays
     */
    setVacationDates: function(localVacationDays, remoteVacationDays, locallyRemovedDays) {
        this.vacationDates = this.getTimestampsFromDays(localVacationDays, remoteVacationDays, locallyRemovedDays);
    },
    
    /**
     * set sickness dates
     * 
     * @param {Object} localSicknessDays
     * @param {Array} remoteSicknessDays
     * @param {Object} locallyRemovedDays
     */
    setSicknessDates: function(localSicknessDays, remoteSicknessDays, locallyRemovedDays) {
        this.sicknessDates = this.getTimestampsFromDays(localSicknessDays, remoteSicknessDays, locallyRemovedDays);
    },
    
    /**
     * set feast dates
     */
    setFeastDates: function(feastDays) {
        this.feastDates = this.getTimestampsFromDays([], feastDays);
    },
    
    /**
     * returns a timestamp from a day
     * 
     * @param {Object} localDays
     * @param {Array} remoteDays
     * @param {Object} locallyRemovedDays
     * 
     * @return {Array}
     */
    getTimestampsFromDays: function(localDays, remoteDays, locallyRemovedDays) {
        
        var offset = new Date().getTimezoneOffset() * 60 * 1000;
        
        var dates = [];
        Ext.iterate(localDays, function(accountId, localdates) { 
            for (var index = 0; index < localdates.length; index++) {
                var newdate = new Date(localdates[index].date.replace(/-/g,'/') + ' AM');
                newdate.setHours(0);
                dates.push((newdate.getTime()));
            }
        });
        
        // find out removed dates
        var remove = [];
        if (locallyRemovedDays) {
            Ext.iterate(locallyRemovedDays, function(accountId, removeDays) {
                for (var index = 0; index < removeDays.length; index++) {
                    remove.push(removeDays[index].date.split(' ')[0]);
                }
            }, this);
        }
        
        // do not mark day as taken, if it is deleted already in the grid
        if (remoteDays) {
            for (var index = 0; index < remoteDays.length; index++) {
                var day = remoteDays[index].date.split(' ')[0];
                if (remove.indexOf(day) == -1) {
                    var newdate = new Date(remoteDays[index].date.replace(/-/g,'/') + ' AM');
                    dates.push(newdate.getTime() + offset);
                }
            }
        }
        
        return dates;
    },
    
    /**
     * is called on year change
     *
     * @param {Date} date
     */
    onYearChange: function(date) {
        this.loadFeastDays(true, false, date);
    },
    
    /**
     * overwrites update function of superclass
     * 
     * @param {} date
     * @param {} forceRefresh
     */
    update : function(date, forceRefresh) {
        Tine.HumanResources.DatePicker.superclass.update.call(this, date, forceRefresh);
        
        if (! this.disableYearChange) {
            var year = parseInt(date.format('Y'));
            if (year !== this.currentYear) {
                if (this.getData().length > 0) {
                    Ext.MessageBox.show({
                        title: this.app.i18n._('Year can not be changed'), 
                        msg: this.app.i18n._('You have already selected some dates from another year. Please create a new record to add dates from another year!'),
                        buttons: Ext.Msg.OK,
                        icon: Ext.MessageBox.WARNING,
                        // jump to the first day of the selected
                        fn: function() {
                            var firstDay = this.store.getFirstDay();
                            this.update(firstDay.get('date'));
                        },
                        scope: this
                    });
                } else {
                    this.previousYear = this.currentYear;
                    this.currentYear = parseInt(date.format('Y'));
                    this.onYearChange(date);
                }
            }
        }
        
        this.updateCellClasses();
    },
    
    /**
     * removes or adds a date on date click
     * 
     * @param {Object} e
     * @param {Object} t
     */
    handleDateClick: function(e, t) {
        // don't handle date click, if this is disabled, or the clicked node doesn't have a timestamp assigned
        if (this.disabled || ! t.dateValue) {
            return;
        }
        // don't handle click on disabled dates defined by contract or feast calendar
        if (Ext.fly(t.parentNode).hasClass('x-date-disabled')) {
            return;
        }
        
        // dont't handle click on already defined sickness days
        if (Ext.fly(t.parentNode).hasClass('hr-date-sickness')) {
            return;
        }
        
        // if clicked date is defined as vacation, and we want to define sickness days, handle click (employee my be sick on vacation)
        // otherwise don't handle (vacation can't be booked on vacation)
        if (this.freetimeType == 'VACATION' && (Ext.fly(t.parentNode).hasClass('hr-date-vacation'))) {
            return;
        }
        
        var date = new Date(t.dateValue),
            existing;
            
        date.clearTime();
        
        if (this.accountPickerActive) {
            var remaining = this.editDialog.getForm().findField('remaining_vacation_days').getValue();
            
            if (remaining == 0) {
                Ext.MessageBox.show({
                    title: this.app.i18n._('No more vacation days'), 
                    msg: this.app.i18n._('The Employee has no more possible vacation days left for this year. Create a new vacation and use another personal account the vacation should be taken from.'),
                    icon: Ext.MessageBox.WARNING,
                    buttons: Ext.Msg.OK
                });
                return;
            }
        } else {
            var remaining = 0;
        }
        
        if (existing = this.store.getByDate(date)) {
            this.store.remove(existing);
            remaining++;
        } else {
            this.store.addSorted(new this.recordClass({date: date, duration: 1}));
            remaining--;
        }
        
        if (this.accountPickerActive) {
            if (this.store.getCount() > 0) {
                this.editDialog.accountPicker.disable();
            } else {
                this.editDialog.accountPicker.enable();
            }
            
            this.editDialog.getForm().findField('remaining_vacation_days').setValue(remaining);
        }
        
        Tine.HumanResources.DatePicker.superclass.handleDateClick.call(this, e, t);
    },
    
    /**
     * updates the cell classes
     */
    updateCellClasses: function() {
        
        this.cells.each(function(c) {
            
            var timestamp = c.dom.firstChild.dateValue;
            
            if (this.store.getByDate(timestamp)) {
                c.addClass('x-date-selected');
            } else {
                c.removeClass('x-date-selected');
            }
            
            if (this.vacationDates.indexOf(timestamp) > -1) {
                c.addClass('hr-date-vacation');
            }
            
            if (this.sicknessDates.indexOf(timestamp) > -1) {
                c.addClass('hr-date-sickness');
            }
            
            if (this.feastDates.indexOf(timestamp) > -1) {
                c.addClass('hr-date-feast');
            }
           
        }, this);
    },
    
    /**
     * returns data for the editDialog
     * 
     * @return {Array}
     */
    getData: function() {
        var ret = [];
        this.store.sort({field: 'date', direction: 'ASC'});
        this.store.query().each(function(record) {
            ret.push(record.data);
        }, this);
        
        return ret;
    }
});
