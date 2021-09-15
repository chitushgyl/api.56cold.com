<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/10/29
 * Time: 13:23
 */

/******WMS系统*******/
Route::group([
    'prefix' => 'tms',
    "middleware"=>['loginCheck','group'],
    'namespace'  => 'Tms',
], function(){
    /** TMS企业认证管理**/
    Route::any('/attestation/attestationList', 'AttestationController@attestationList');
    Route::any('/attestation/attestationPage', 'AttestationController@attestationPage');
    Route::any('/attestation/attestationFail', 'AttestationController@attestationFail');//认证失败
    Route::any('/attestation/attestationPass', 'AttestationController@attestationPass');//认证成功
    Route::any('/attestation/attestationT', 'AttestationController@attestationT');//认证成功
    Route::any('/attestation/details', 'AttestationController@details');//认证成功

    /** TMS落地配城市设置**/
    Route::any('/city/cityList', 'CityController@cityList');
    Route::any('/city/cityPage', 'CityController@cityPage');
    Route::any('/city/createCity', 'CityController@createCity');
    Route::any('/city/addCity', 'CityController@addCity');
    Route::any('/city/cityUseFlag', 'CityController@cityUseFlag');
    Route::any('/city/cityDelFlag', 'CityController@cityDelFlag');
    Route::any('/city/details', 'CityController@details');//认证成功

    /**TMS车辆类型管理**/
    Route::any('/type/typeList', 'TypeController@typeList');
    Route::any('/type/typePage', 'TypeController@typePage');
    Route::any('/type/createType','TypeController@createType');
    Route::any('/type/details', 'TypeController@details');
    Route::any('/type/getType','TypeController@getType');

    Route::group([
        "middleware"=>['daily'],
    ], function(){
    Route::any('/type/addType','TypeController@addType');
    Route::any('/type/typeUseFlag', 'TypeController@typeUseFlag');
    Route::any('/type/typeDelFlag', 'TypeController@typeDelFlag');
    Route::any('/type/import', 'TypeController@import');
    });

    /**TMS车辆管理**/
    Route::any('/car/carList', 'CarController@carList');
    Route::any('/car/carPage', 'CarController@carPage');
    Route::any('/car/createCar','CarController@createCar');
    Route::any('/car/details', 'CarController@details');
    Route::any('/car/getCar','CarController@getCar');
    Route::any('/car/addCar','CarController@addCar');
    Route::any('/car/carUseFlag', 'CarController@carUseFlag');
    Route::any('/car/carDelFlag', 'CarController@carDelFlag');
    Route::any('/car/import', 'CarController@import');
    Route::any('/car/execl', 'CarController@execl');
    Route::group([
        "middleware"=>['daily'],
    ], function(){
        Route::any('/car/addCar','CarController@addCar');
        Route::any('/car/carUseFlag', 'CarController@carUseFlag');
        Route::any('/car/carDelFlag', 'CarController@carDelFlag');
        Route::any('/car/import', 'CarController@import');
        Route::any('/car/execl', 'CarController@execl');
    });

    /**TMS车辆业务公司管理**/
    Route::any('/group/groupList', 'GroupController@groupList');
    Route::any('/group/groupPage', 'GroupController@groupPage');
    Route::any('/group/createGroup','GroupController@createGroup');
    Route::any('/group/details', 'GroupController@details');
    Route::any('/group/getCompany','GroupController@getCompany');
    Route::any('/group/getGroup','GroupController@getGroup');

    Route::any('/customer/customerList', 'CustomerController@customerList');
    Route::any('/customer/customerPage', 'CustomerController@customerPage');

    Route::any('/driver/driverList', 'DriverController@driverList');
    Route::any('/driver/driverPage', 'DriverController@driverPage');
    Route::any('/driver/addDriver', 'DriverController@addDriver');

    Route::group([
        "middleware"=>['daily'],
    ], function(){
    Route::any('/group/addGroup','GroupController@addGroup');
    Route::any('/group/groupUseFlag', 'GroupController@groupUseFlag');
    Route::any('/group/groupDelFlag', 'GroupController@groupDelFlag');
    Route::any('/group/import', 'GroupController@import');
    Route::any('/group/execl', 'GroupController@execl');
    });


    /**TMS联系人管理**/
    Route::any('/contacts/contactsList', 'ContactsController@contactsList');
    Route::any('/contacts/contactsPage', 'ContactsController@contactsPage');
    Route::any('/contacts/createContacts','ContactsController@createContacts');
    Route::any('/contacts/details', 'ContactsController@details');
    Route::any('/contacts/getContacts','ContactsController@getContacts');

    Route::group([
        "middleware"=>['daily'],
    ], function(){
    Route::any('/contacts/addContacts','ContactsController@addContacts');
    Route::any('/contacts/contactsUseFlag', 'ContactsController@contactsUseFlag');
    Route::any('/contacts/contactsDelFlag', 'ContactsController@contactsDelFlag');
    Route::any('/contacts/import', 'ContactsController@import');
    Route::any('/contacts/execl', 'ContactsController@execl');
    });


    /**TMS地址管理**/
    Route::any('/address/addressList', 'AddressController@addressList');
    Route::any('/address/addressPage', 'AddressController@addressPage');
    Route::any('/address/createAddress','AddressController@createAddress');
    Route::any('/address/details', 'AddressController@details');
    Route::any('/address/getAddress','AddressController@getAddress');
    Route::any('/address/execl', 'AddressController@execl');

    Route::group([
        "middleware"=>['daily'],
    ], function(){
    Route::any('/address/addAddress','AddressController@addAddress');
    Route::any('/address/addressUseFlag', 'AddressController@addressUseFlag');
    Route::any('/address/addressDelFlag', 'AddressController@addressDelFlag');
    Route::any('/address/import', 'AddressController@import');
    });

    /**TMS线路管理**/
    Route::any('/line/lineList', 'LineController@lineList');
    Route::any('/line/linePage', 'LineController@linePage');
    Route::any('/line/createLine','LineController@createLine');
    Route::any('/line/details', 'LineController@details');
    Route::any('/line/getLine','LineController@getLine');
    Route::any('/line/excel','LineController@excel');
    Route::any('/line/get_line','LineController@get_line');
    Route::any('/line/import','LineController@import');
    Route::any('/line/count_price','LineController@count_price');
    Route::any('/line/line_list','LineController@line_list');
    Route::group([
        "middleware"=>['daily'],
    ], function(){
        Route::any('/line/addLine','LineController@addLine');
        Route::any('/line/lineListDeleteFlag','LineController@lineListDeleteFlag');
        Route::any('/line/lineUseFlag', 'LineController@lineUseFlag');
        Route::any('/line/lineDelFlag', 'LineController@lineDelFlag');


    });




    /**TMS订单管理**/
    Route::any('/order/orderList', 'OrderController@orderList');
    Route::any('/order/orderPage', 'OrderController@orderPage');
    Route::any('/order/createOrder','OrderController@createOrder');
    Route::any('/order/details', 'OrderController@details');
    Route::any('/order/getOrder','OrderController@getOrder');
    Route::any('/order/addOrder','OrderController@addOrder');
    Route::any('/order/orderUseFlag', 'OrderController@orderUseFlag');
    Route::any('/order/orderDelFlag', 'OrderController@orderDelFlag');
	Route::any('/order/addOrder','OrderController@addOrder');
	Route::any('/order/orderCancel','OrderController@orderCancel');
	Route::any('/order/orderDone','OrderController@orderDone');
	Route::any('/order/add_order','OrderController@add_order');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

        Route::any('/order/orderUseFlag', 'OrderController@orderUseFlag');
        Route::any('/order/orderDelFlag', 'OrderController@orderDelFlag');
    });

    /**TMS调度管理**/
    Route::any('/dispatch/dispatchList', 'DispatchController@dispatchList');
    Route::any('/dispatch/dispatchPage', 'DispatchController@dispatchPage');
    Route::any('/dispatch/createDispatch','DispatchController@createDispatch');
    Route::any('/dispatch/dispatchOrder','DispatchController@dispatchOrder');
    Route::any('/dispatch/addDispatch','DispatchController@addDispatch');
    Route::any('/dispatch/details', 'DispatchController@details');
    Route::any('/dispatch/getDispatch','DispatchController@getDispatch');
    Route::any('/dispatch/dispatchCancel','DispatchController@dispatchCancel');
    Route::any('/dispatch/carriageDone','DispatchController@carriageDone');
    Route::any('/dispatch/uploadReceipt','DispatchController@uploadReceipt');
    Route::group([
        "middleware"=>['daily'],
    ], function(){
        Route::any('/dispatch/online','DispatchController@online');
        Route::any('/dispatch/unline','DispatchController@unline');
    });

    /**TMS费用管理**/
    Route::any('/money/moneyList', 'MoneyController@moneyList');
    Route::any('/money/moneyPage', 'MoneyController@moneyPage');
	Route::any('/money/details', 'MoneyController@details');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });

    /**TMS费用管理**/
    Route::any('/settle/settleList', 'SettleController@settleList');
    Route::any('/settle/settlePage', 'SettleController@settlePage');
    Route::any('/settle/createSettle','SettleController@createSettle');
    Route::any('/settle/addSettle','SettleController@addSettle');
    Route::any('/settle/createGathering','SettleController@createGathering');
    Route::any('/settle/addGathering','SettleController@addGathering');
    Route::any('/settle/details', 'SettleController@details');
    Route::any('/settle/payment', 'SettleController@payment');
    Route::any('/settle/updateSettle', 'SettleController@updateSettle');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });

    /** TMS线上订单管理**/
    Route::any('/online/onlineList', 'OnlineController@onlineList');
    Route::any('/online/onlinePage', 'OnlineController@onlinePage');
    Route::any('/online/createDispatch', 'OnlineController@createDispatch');
    Route::any('/online/addOrder', 'OnlineController@addOrder');
    Route::any('/online/details', 'OnlineController@details');
    Route::any('/online/orderCancel', 'OnlineController@orderCancel');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });

    /** TMS提现管理**/
    Route::any('/wallet/walletList', 'WalletController@walletList');
    Route::any('/wallet/walletPage', 'WalletController@walletPage');
    Route::any('/wallet/walletInfo', 'WalletController@walletInfo');
    Route::any('/wallet/walletPass', 'WalletController@walletPass');
    Route::any('/wallet/withdrawMoney', 'WalletController@withdrawMoney');
    Route::any('/wallet/getAccount', 'WalletController@getAccount');
    Route::any('/wallet/details', 'WalletController@details');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });

    /*** 推送**/
    Route::any('/push/pushList', 'PushController@pushList');
    Route::any('/push/pushPage', 'PushController@pushPage');
    Route::any('/push/addPush', 'PushController@addPush');
    Route::any('/push/pushObject', 'PushController@pushObject');
    Route::any('/push/toPush', 'PushController@toPush');
    Route::any('/push/pushDelFlag', 'PushController@pushDelFlag');
    Route::any('/push/pushUseFlag', 'PushController@pushUseFlag');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });

    /** TMS开票**/
    Route::any('/bill/billList','BillController@billList');
    Route::any('/bill/billPage','BillController@billPage');
    Route::any('/bill/createBill','BillController@createBill');
    Route::any('/bill/addBill','BillController@addBill');
    Route::any('/bill/billDelFlag','BillController@billDelFlag');
    Route::any('/bill/details','BillController@details');
    Route::any('/bill/orderList','BillController@orderList');
    Route::any('/bill/order_list','BillController@order_list');
    Route::any('/bill/commonBillList','BillController@commonBillList');
    Route::any('/bill/commonBillPage','BillController@commonBillPage');
    Route::any('/bill/createCommonBill','BillController@createCommonBill');
    Route::any('/bill/addCommonBill','BillController@addCommonBill');
    Route::any('/bill/useCommonBill','BillController@useCommonBill');
    Route::any('/bill/delCommonBill','BillController@delCommonBill');
    Route::any('/bill/billDetails','BillController@billDetails');
    Route::any('/bill/billTitleList','BillController@billTitleList');
    Route::any('/bill/billTitlePage','BillController@billTitlePage');
    Route::any('/bill/billSuccess','BillController@billSuccess');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });

    /** TMS仓库**/
    Route::any('/warehouse/warehouseList', 'WarehouseController@warehouseList');
    Route::any('/warehouse/warehousePage', 'WarehouseController@warehousePage');
    Route::any('/warehouse/createWarehouse', 'WarehouseController@createWarehouse');
    Route::any('/warehouse/addWarehouse','WarehouseController@addWarehouse');
    Route::any('/warehouse/warehouseUseFlag','WarehouseController@warehouseUseFlag');
    Route::any('/warehouse/warehouseDelFlag','WarehouseController@warehouseDelFlag');
    Route::any('/warehouse/details','WarehouseController@details');
    Route::any('/warehouse/import', 'WarehouseController@import');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });

    /** TMS评论 ***/
    Route::any('/discuss/discussList','DiscussController@discussList');
    Route::any('/discuss/discussPage','DiscussController@discussPage');
    Route::any('/discuss/createDiscuss','DiscussController@createDiscuss');
    Route::any('/discuss/addDiscuss','DiscussController@addDiscuss');
    Route::any('/discuss/delFlag','DiscussController@delFlag');
    Route::any('/discuss/billDetails','DiscussController@billDetails');
    Route::group([
        "middleware"=>['daily'],
    ], function(){

    });
});


