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
    private    $accessToken;

    public function __construct( ) {
        $this->client_id = env('ZOHO_CLIENT_ID');
        $this->client_secret = env('ZOHO_CLIENT_SECRET');
        $this->refresh_token = env('ZOHO_REFRESH_TOKEN');
        $this->organization_id = env('ZOHO_ORGANIZATION_ID');
    }

    
    public function runAllApisCycle(Request $request) {

        $customer_phones= array();
        $customer_ids   = array(); 
        $adminEmails     = array();
        $accessToken = $this->generateAccessToken();
        $admins      = $this->getAdmins($accessToken);        
        foreach($admins as $admin) {
            $adminEmails[] = $admin['email'];
        } 
        $customers = $this->getAllOrganizationCustomersInfo($accessToken);
        /*
        **customer_name : alaa bat7a
          customer_phone: 0791883838383
          date:20120-05-28
        **
        **
        **
        */                      
        if($request) {
            $customer_name             = $request->get('customer_name');
            $customer_phone            = $request->get('customer_phone');
            $customer_address          = $request->get('customer_address');
            $customer_shipping_address = $request->get('customer_shipping_address');
            $salesorder_number         = $request->get('salesorder_number');
            $date                      = $request->get('date','');
            $shipment_date             = $request->get('shipment_date','');
            $reference_number          = $request->get('reference_number','');
            $line_items                = $request->get('line_items','');//array of items
            $items_qty                 = $request->get('qty');
            $notes                     = $request->get('notes','');
            $terms                     = $request->get('terms','');
            $discount                  = $request->get('discount','');
            $discount_type             = $request->get('discount_type','');
            $shipping_charge           = $request->get('shipping_charge','');
            $delivery_method           = $request->get('delivery_method');
            $shipping_address_id       = $request->get('shipping_address_id');
        }else{
            return 'Bad Request';
        }
        foreach($customers as $customer){
            $customer_phones[]         = $customer['phone'];
            $customer_ids[]            = $customer['contact_id'];
        }
        if(!in_array($customer_phone,$customer_phones)) {
            $customer_info             = $this->createCustomer($accessToken,$customer_name,$customer_phone,$customer_address,$customer_shipping_address);
            $customer_id               = $customer_info['customer_id'];
        }else{
            $customer_id               = $this->getCustomerIdByPhone($customers,$customer_phone);           
        }
        $saleOrder_info                = $this->createSaleOrder($request,$accessToken,$customer_id);
        $invoice_info                  = $this->createInvoice($saleOrder_info,$accessToken);
        $admins                        = $this->getAdmins($accessToken);
        $invoice_id                    = $invoice_info['invoice_id'];
        $customerContacts              = $this->getCustomerById($customer_id);
        $customer_email                = $customerContacts['email'];
        $emails[]                      = $customer_email;
        foreach ($admins as $admin ) {
            $emails[] = $admin['email'];
        }
         $this->sendEmail($invoice_id,$emails);
        //todo send invoice to printer
    }

    public function generateAccessToken() {        
        
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

    public function getAllOrganizationCustomersInfo($accessToken) {

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
                        'Authorization: Zoho-oauthtoken '.$accessToken.'',
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

public function createSaleOrder(Request $request,$accessToken,$customer_id) {       
    
    $date = $request->get('date');
    $items= $request->get('line_items');    
    // $itemsAsString = $this->buildSalesOrderItemsAsString($items);
    $curl = curl_init();
    $jsonstring = '{"oauthscope":"ZohoInventory.salesorders.CREATE","customer_id":'.$customer_id.',"date":"'.$date.'","shipment_date":"2021-06-02","line_items":'.$items.'}';
    
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
                'Authorization: Zoho-oauthtoken '.$accessToken.'',
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
    $salesorderInfo = $responseBody['salesorder'];
return $salesorderInfo;
}


public function createInvoice ($salesOrderData,$accessToken) {
    
    $customer_id   = $salesOrderData['customer_id'];
    $shipment_date = $salesOrderData['shipment_date'];
    $date          = $salesOrderData['date'];
    $items         = $salesOrderData['line_items'];
    $customer_name = $salesOrderData['customer_name'];
    $itemsAsString = $this->buildInvoiceItemsAsString($items);
    $curl = curl_init();
    $jsonstring = '{"oauthscope":"ZohoInventory.invoices.CREATE","customer_id":'.$customer_id.',"customer_name":"'.$customer_name.'","date":"'.$date.'","shipment_date":"2015-06-02",,"line_items":'.$itemsAsString.',"documents":[{"can_send_in_mail":true,"file_name":"sample.pdf","file_type":"pdf","file_size_formatted":"116.8KB","attachment_order":1,"document_id":16115000000096068,"file_size":11957}]}';
    $jsonstring = urlencode($jsonstring);
    curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/invoices?&organization_id=741141186&ignore_auto_number_generation=false',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'JSONString='. $jsonstring,
            CURLOPT_HTTPHEADER => array(
                    'Authorization: Zoho-oauthtoken '.$accessToken.'',
                    'Content-Type: application/x-www-form-urlencoded',    
            ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    echo $response;
}

public function createCustomer($accessToken,$customer_name,$customer_phone,$customer_address,$customer_shipping) {

    $address     = !empty($customer_address['address'])   ? $customer_address['address'] : '';
    $street      = !empty($customer_address['street2'])   ? $customer_address['street2'] : '';
    $city        = !empty($customer_address['city'])      ? $customer_address['city']    : '';
    $state       = !empty($customer_address['state'])     ? $customer_address['state']   : '';
    $country     = !empty($customer_address['country'])   ? $customer_address['country'] : '';
    $zip         = !empty($customer_address['zip'])       ? $customer_address['zip']     : '';
    
    $sAddress    = !empty($customer_shipping['address']) ? $customer_shipping['address'] : '';
    $sStreet     = !empty($customer_shipping['street2']) ? $customer_shipping['street2'] : '';
    $sCity       = !empty($customer_shipping['city'])    ? $customer_shipping['city']    : '';
    $sState      = !empty($customer_shipping['state'])   ? $customer_shipping['state']   : '';
    $sCountry    = !empty($customer_shipping['country']) ? $customer_shipping['country'] : '';
    $sZip        = !empty($customer_shipping['zip'])     ? $customer_shipping['zip']     : '';

    $spiltedName = $this->split_name($customer_name);
    $first_name  = $spiltedName[0];
    $last_name   = $spiltedName[1];
    


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
            'Authorization: Zoho-oauthtoken '.$accessToken.'',
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
public function buildSalesOrderItemsAsString($items) {
    //  $items = json_decode($items,true);
    //if(is_array($items)){
    $arrayAsString = '[';    
    foreach($items as $item){
        $arrayAsString .= '{"item_id":'.$item['item_id'].',"name":"'.$item['name'].'","description":"'.$item['desc'].'","quantity":'.$item['qty'].',"item_total":'.$item['total'].'},';
    }
    $arrayAsString = rtrim($arrayAsString,',');
    $arrayAsString .=']';
    return $arrayAsString;
// }
// return 'items line should be an array';
}

public function buildInvoiceItemsAsString($items) {

    $arrayAsString     = '[';
    foreach($items as $item){
        $arrayAsString .='{""item_id":'.$item['item_id'].',"name":"'.$item['name'].'","description":"'.$item['description'].'","rate":'.$item['rate'].',"quantity":'.$item['quantity'].',"salesorder_item_id":'.$item['line_item_id'].',"item_total":""},';
    }
        $arrayAsString  = rtrim($arrayAsString,',');
        $arrayAsString .=']';
        return $arrayAsString;
   
}

public function buildEmailsArrayAsString($emails) {
    
    $arrayAsString     = '[';
    foreach($emails as $email){
        $arrayAsString .='"'.$email.'",';
    }
        $arrayAsString  = rtrim($arrayAsString,',');
        $arrayAsString .=']';
        return $arrayAsString;
}

public function getCustomerIdByPhone($customers,$phone) {

    foreach ($customers as $customer) {
        if ($customer['phone'] == $phone) {
            return $customer['contact_id'];
            break;
        }
    }

    return '';

}
public function getAdmins($accessToken) {
    $httpClient1 = new Client([
        'base_uri' => 'https://inventory.zoho.com/api/v1/users',
    ]);
    $response = $httpClient1->request('GET',
        '?organization_id=741141186&type=AdminUsers',[
            'headers' => [
                'Authorization' =>'Zoho-oauthtoken '.$accessToken.''// 'Zoho-oauthtoken 1000.8fee4dd65af86acc34e9b49d1477ac84.6b8a1f05e424a19d7e9c5f89130c7413'
            ]
        ]
    );
    if($response) {
        $responseBody = json_decode($response->getbody(),true);
    }else{
        return 'there is something goes wrong';
    }
         
    return $responseBody['users'];
    }


    public function sendEmail($invoice_id,$emails) {//todo pass invoice NO
    $emailsAsString = $this->buildEmailsArrayAsString($emails);
    $curl = curl_init();
    $jsonstring = '
    {
        "send_from_org_email_id": false,
        "to_mail_ids": '.$emailsAsString.',        
        "subject": "Invoice from Zillium Inc (Invoice#: INV-00001)",
        "body": "Dear Customer,         <br><br><br><br>Thanks for your business.         <br><br><br><br>The invoice INV-00001 is attached with this email. You can choose the easy way out and <a href= https://invoice.zoho.com/SecurePayment?CInvoiceID=b9800228e011ae86abe71227bdacb3c68e1af685f647dcaed747812e0b9314635e55ac6223925675b371fcbd2d5ae3dc  >pay online for this invoice.</a>         <br><br>Here\'s an overview of the invoice for your reference.         <br><br><br><br>Invoice Overview:         <br><br>Invoice  : INV-00001         <br><br>Date : 05 Aug 2013         <br><br>Amount : $541.82         <br><br><br><br>It was great working with you. Looking forward to working with you again.<br><br><br>\\nRegards<br>\\nZillium Inc<br>\\n\","
    }';
    
    
    $jsonstring = urlencode($jsonstring);    
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://inventory.zoho.com/api/v1/invoices/'.$invoice_id.'/email?organization_id=741141186',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'JSONString='. $jsonstring,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Zoho-oauthtoken '.$accessToken.'',
            'Content-Type: application/x-www-form-urlencoded',    
        ),
    ));
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
    $response = curl_exec($curl); 
    }    
    public function getCustomerById($id) {

        $httpClient1 = new Client([
            'base_uri' => 'https://inventory.zoho.com/api/v1/contacts/'.$id.'?organization_id=10234695
            ',
        ]);
        $response = $httpClient1->request('GET',
            '?organization_id=741141186&type=AdminUsers',[
                'headers' => [
                    'Authorization' =>'Zoho-oauthtoken '.$accessToken.''// 'Zoho-oauthtoken 1000.8fee4dd65af86acc34e9b49d1477ac84.6b8a1f05e424a19d7e9c5f89130c7413'
                ]
            ]
        );
        if($response) {
            $responseBody = json_decode($response->getbody(),true);
        }else{
            return 'there is something goes wrong';
        }
             
        return $responseBody['contact']['contact_persons'];

    }
}









