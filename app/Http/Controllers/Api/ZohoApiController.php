<?php

namespace App\Http\Controllers\Api;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ZohoApiController extends Controller
{
    protected  $client_id;
    protected  $client_secret;
    protected  $refresh_token;
    protected  $organization_id;
    protected  $ignore_auto_number_generation = false;

    public function __construct( ) {
        $this->client_id = env('ZOHO_CLIENT_ID');
        $this->client_secret = env('ZOHO_CLIENT_SECRET');
        $this->refresh_token = env('ZOHO_REFRESH_TOKEN');
        $this->organization_id = env('ZOHO_ORGANIZATION_ID');
    }


    public function runAllApisCycle() {
        $customers = $this->getAllOrganizationCustomersInfo();
        $customer_phones= array();
        $customer_ids   = array();
        foreach($customers as $customer){

            $customer_phones[] = $customer['phone'];
            $customer_ids[]    = $customer['customer_id'];
        }        
        if($requet) {
            $customer_name = $request->get('customer_name');
            $customer_phone =  $request->get('customer_phone');
            $customer_address = $request->get('customer_address');
            $customer_shipping_address = $request->get('customer_shipping_address');
            $salesorder_number = $request->get('salesorder_number');
            $date = $request->get('date','');
            $shipment_date = $request->get('shipment_date','');
            $reference_number = $request->get('reference_number','');
            $line_items = $request->get('line_items','');//array of items
            $items_qty  = $request->get('qty');
            $notes = $request->get('notes','');
            $terms = $request->get('terms','');
            $discount = $request->get('discount','');
            $discount_type = $request->get('discount_type','');
            $shipping_charge = $request->get('shipping_charge','');
            $delivery_method = $request->get('delivery_method');
            $shipping_address_id = $request->get('shipping_address_id');
        }else{
            return 'Bad Request';
        }

        if(!in_array($customer_phone,$customers_phones)) {
            $customer_info = $this->createCustomer($customer_name,$customer_phone,$customer_address,$customer_shipping_address);
            $customer_id = $customer_info['customer_id'];
            $saleOrder_info = $this->createSaleOrder($request,$customer_id);
        }

    }

    public function generateAccessToken() {

        // $items = [
        //     [
        //     'id' => 122222,
        //     'name' => 'dell pc',
        //     'desc' => 'this is desc',
        //     'qty' => 3,
        //     'total' => 444],
        //     [
        //         'id' => 12224422,
        //         'name' => 'hp pc',
        //         'desc' => 'this is desc',
        //         'qty' => 3,
        //         'total' => 444]
        // ];
        // $text = $this->buildItemsAsString($items);
        echo $text;exit;
        $httpClient1 = new Client([
            'base_uri' => config('app.api_base_url_for_access_token'),
        ]);
        $response = $httpClient1->request('POST',
            '?refresh_token='.$this->refresh_token.'&client_id='.$this->client_id.'&client_secret='.$this->client_secret.'&redirect_uri=https://inventory.zoho.com&grant_type=refresh_token'
        );
        if($response) {
            $responseBody = json_decode($response->getbody(),true);
        }else{
            return 'there is something goes wrong';
        }
               
        return $responseBody['access_token'];
}

public function getAllOrganizationCustomersInfo() {

    $curl = curl_init();    
    curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/contacts?=&organization_id=741141186',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',    
            CURLOPT_HTTPHEADER => array(
                'Authorization: Zoho-oauthtoken 1000.a8ace377ae5c2f51a00018f73b6665e2.d82919d7f49be0b2ba44bdf5a76618c0',
                'Content-Type: application/x-www-form-urlencoded',        
            ),
    ));

    curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
    $response = curl_exec($curl);
    if($response) {
        $responseBody = json_decode($response,true);
    }else{
        return 'there is something goes wrong';
    }
    curl_close($curl);
    $customers = $responseBody['contacts'];
return $customers;
}

public function createSaleOrder(Request $request,$customer_id) {       
    
    $date = $request->get('date');
    $items[]= $request->get('line_items');    
    $itemsAsString = $this->buildItemsAsString($items) ;
    $curl = curl_init();
    $jsonstring = '{"oauthscope":"ZohoInventory.salesorders.CREATE","customer_id":'.$customer_id.',"date":"'.$date.'","shipment_date":"2015-06-02","line_items":'.$itemsAsString.'}';
    echo $jsonstring;exit;
    $jsonstring = urlencode($jsonstring);
    curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/salesorders?=&organization_id=741141186&ignore_auto_number_generation=false',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'JSONString='. $jsonstring,
            CURLOPT_HTTPHEADER => array(
                                        'Authorization: Zoho-oauthtoken 1000.ce31699a38f35fd77e552a002d5d4517.98fcfb9766bceeebb5bded0ec43d31dc',
                                        'Content-Type: application/x-www-form-urlencoded',
                                        'Cookie: BuildCookie_741141186=1; f73898f234=5b539f0fab928089167210a2d2de45f1; zomcscook=51f04b5a-1df4-40e1-8fcb-a84ca8698a3d; _zcsr_tmp=51f04b5a-1df4-40e1-8fcb-a84ca8698a3d; JSESSIONID=414D75DB2466331EB9E0D10E510B7DD4'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        echo $response;
}


public function createInvoice () {

    $curl = curl_init();
    $jsonstring = '{"oauthscope":"ZohoInventory.invoices.CREATE","customer_id":2540554000000074003,"invoice_number":"INV-002909203","date":"2021-02-28","shipment_date":"2015-06-02","custom_fields":[{}],"reference_number":"REF-S-00003","line_items":[{"item_id":2540554000000073331,"salesorder_item_id": 2540554000000098020,"name":"Laptop-white/15inch/dell","description":"Justasampledescription.","rate":122,"quantity":2,"unit":"qty","item_total":244,"tax_name": "Standard Rate","tax_percentage": 5}],"documents":[{"can_send_in_mail":true,"file_name":"sample.pdf","file_type":"pdf","file_size_formatted":"116.8KB","attachment_order":1,"document_id":16115000000096068,"file_size":11957}],"place_of_supply":""}';
    $jsonstring = urlencode($jsonstring);
    curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/invoices?&organization_id=741141186&ignore_auto_number_generation=true',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'JSONString='. $jsonstring,
            CURLOPT_HTTPHEADER => array(
                            'Authorization: Zoho-oauthtoken 1000.a45c67069b3444f7bbc96197739ae154.ecb26d9b5dd4ca9cbd56d559886bdf9a',
                            'Content-Type: application/x-www-form-urlencoded',    
            ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    echo $response;
}

public function createCustomer($customer_name,$customer_phone,$customer_address,$customer_shipping) {

    $address = !empty($customer_address['address']) ? $customer_address['address'] : '';
    $street  = !empty($customer_address['street2']) ? $customer_address['street2'] : '';
    $city    = !empty($customer_address['city'])    ? $customer_address['city']    : '';
    $state   = !empty($customer_address['state'])   ? $customer_address['state']   : '';
    $country = !empty($customer_address['country']) ? $customer_address['country'] : '';
    $zip     = !empty($customer_address['zip'])     ? $customer_address['zip']     : '';
    
    $sAddress = !empty($customer_shipping['address']) ? $customer_shipping['address'] : '';
    $sStreet  = !empty($customer_shipping['street2']) ? $customer_shipping['street2'] : '';
    $sCity    = !empty($customer_shipping['city'])    ? $customer_shipping['city']    : '';
    $sState   = !empty($customer_shipping['state'])   ? $customer_shipping['state']   : '';
    $sCountry = !empty($customer_shipping['country']) ? $customer_shipping['country'] : '';
    $sZip     = !empty($customer_shipping['zip'])     ? $customer_shipping['zip']     : '';

    $spiltedName = $this->split_name($customer_name);
    $first_name = $spiltedName[0];
    $last_name  = $spiltedName[1];
    


    $curl = curl_init();
    $jsonstring = '
    {"contact_name": "'.$customer_name.'","company_name": "'.$customer_name.'","contact_type": "customer","billing_address": {"attention": "Mr.'.$first_name.'","address": "'.$address.'","street2": "'.$street.'","city": "'.$city.'","state": "'.$state.'","country": "'.$country.'"},"shipping_address": {"attention": "Mr.'.$first_name.'","address": "'.$sAddress.'","street2": "'.$sStreet.'","city": "'.$sCity.'","state": "'.$sState.'","zip": 94588,"country": "'.$sCountry.'"},"contact_persons": [{"alutation": "Mr","first_name": "'.$first_name.'","last_name": "'.$last_name.'","email": "","phone": "'.$customer_phone.'","mobile":"'.$customer_phone.'","is_primary_contact": true }]}';
    
    
    $jsonstring = urlencode($jsonstring);    
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://inventory.zoho.com/api/v1/contacts?&organization_id=741141186&ignore_auto_number_generation=true',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'JSONString='. $jsonstring,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Zoho-oauthtoken 1000.773f3702429f05db6f899c2e2806677b.82d977e6ceacaa0f600adcee946ef559',
            'Content-Type: application/x-www-form-urlencoded',    
        ),
    ));
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
    $response = curl_exec($curl);
    if($response) {
        $responseBody = json_decode($response,true);
    }else{
        return 'there is something goes wrong';
    }
    curl_close($curl);
    $customer_info = $responseBody['contact'];
    
}


public function split_name($name) {
    $name = trim($name);
    $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
    $first_name = trim( preg_replace('#'.preg_quote($last_name,'#').'#', '', $name ) );
    return array($first_name, $last_name);
}

// "line_items":[{"item_id":2540554000000073331,"name":"Laptop-white/15inch/dell","description":"Justasampledescription.","rate":122,"quantity":2,"unit":"qty","item_total":244}]
public function buildItemsAsString($items) {
    
    $arrayAsString = '[';
    // var_dump($items);exit;
    foreach($items as $item){
        $arrayAsString .= '{"item_id":'.$item['id'].',"name":"'.$item['name'].'","description":"'.$item['desc'].'","quantity":'.$item['qty'].',"item_total":'.$item['total'].'},';
    }
    $arrayAsString = rtrim($arrayAsString,',');
    $arrayAsString .=']';
    return $arrayAsString;
}


}




