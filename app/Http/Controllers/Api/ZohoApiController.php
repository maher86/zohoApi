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
    protected  $url;
    protected  $username;
    protected  $password;
    protected  $token;

    public function __construct( ) {
        $this->client_id = env('ZOHO_CLIENT_ID');
        $this->client_secret = env('ZOHO_CLIENT_SECRET');
        $this->refresh_token = env('ZOHO_REFRESH_TOKEN');
        $this->organization_id = env('ZOHO_ORGANIZATION_ID');
        $this->url = env('TRANSCROP_API_URL');
        $this->username = env('TRANSCROP_USERNAME');
        $this->password = env('TRANSCROP_PASSWORD');
        $this->token = "";
    }

    
    public function runAllApisCycle(Request $request) {

        $customer_phones= array();
        $customer_ids   = array(); 
        $adminEmails     = array();
        $accessToken = $this->generateAccessToken();
        $admins      = $this->getAdmins($accessToken['data']);              
        foreach($admins['data'] as $admin) {
            $adminEmails[] = $admin['email'];
        } 
        $customers = $this->getAllOrganizationCustomersInfo($accessToken['data']);
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
        $customers = json_decode($customers,true);
        foreach($customers['data'] as $customer){
            $customer_phones[]         = $customer['phone'];
            $customer_ids[]            = $customer['contact_id'];
        }
        if(!in_array($customer_phone,$customer_phones)) {
            $customer_info             = $this->createCustomer($accessToken['data'],$customer_phone,$customer_name,$customer_address,$customer_shipping_address);
            $customer_id               = $customer_info['data']['customer_id'];
        }else{
            $customer_id               = $this->getCustomerIdByPhone($customers['data'],$customer_phone);           
        }
        $saleOrder_info                = $this->createSaleOrder($request,$accessToken['data'],$customer_id);
        $saleOrder_info                 = json_decode($saleOrder_info,true);        
        $invoice_info                  = $this->createInvoice($saleOrder_info['data'],$accessToken['data']);
        $invoice_info                  = json_decode($invoice_info,true);
        $admins                        = $this->getAdmins($accessToken['data']);
        $invoice_id                    = $invoice_info['data']['invoice_id'];
        $customerContacts              = $this->getCustomerById($customer_id,$accessToken['data']);
        $customer_email                = $customerContacts['data']['email'];
        $emails[]                      = $customer_email;
        foreach ($admins['data'] as $admin ) {
            $emails[] = $admin['email'];
        }
         $this->sendEmail($invoice_id,$emails,$accessToken['data']);
         $responseFromPrint = $this->printInvoice($invoice_id,$accessToken['data']);
        //  $this->create_task($request);
        if($responseFromPrint['success'] == true){

            return json_encode(['success'=>true,'message'=>'the full cycle is done']);
        }else{
            return json_encode(['success'=>false,'message'=>'there is something wrong']);
        }
        
    }

    public function generateAccessToken() {        
        
        $httpClient1 = new Client([
            'base_uri' => config('app.api_base_url_for_access_token'),
        ]);
        $response = $httpClient1->request('POST',
            '?refresh_token='.$this->refresh_token.'&client_id='.$this->client_id.'&client_secret='.$this->client_secret.'&redirect_uri=https://inventory.zoho.com&grant_type=refresh_token'
        );
        $http_code = $response->getStatusCode();
        $responseBody = json_decode($response->getbody(),true);
        if($http_code == 200) {            
            $responseArr  = ['success'=>true,'message'=>'the token has been generated successfully','data'=>$responseBody['access_token']];
            return $responseArr;        
        }else{            
            $responseArr  = ['success'=>false,'message'=>'the token couldn\'t generate'];
            return json_encode($responseArr);
        }
        // if($response) {
        //     $responseBody = json_decode($response->getbody(),true);
        // }else{
        //     return 'there is something goes wrong';
        // }
               
        // return $responseBody['access_token'];
    }

    public function getAllOrganizationCustomersInfo($accessToken) {

            $curl = curl_init();    
            curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://inventory.zoho.com/api/v1/contacts?organization_id='.$this->organization_id.'',
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
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            $responseBody = json_decode($response,true);
            if($http_code == 200) {                
                $responseArr = ['success'=>true,'message'=>$responseBody['message'],'data'=>$responseBody['contacts']];
                return json_encode($responseArr);
            }else{
                $responseArr = ['success'=>false,'message'=>$responseBody['message'],'data'=>$responseBody['contacts']];
                return json_encode($responseArr);
            }           
    }

public function createSaleOrder(Request $request,$accessToken,$customer_id) {       
    
    $date = $request->get('date');
    $itemsAsString= $request->get('line_items');   
    $itemsAsString = json_encode($itemsAsString);
    $curl = curl_init();
    $jsonstring = '{"oauthscope":"ZohoInventory.salesorders.CREATE","customer_id":'.$customer_id.',"date":"'.$date.'","shipment_date":"2021-06-02","line_items":'.$itemsAsString.'}';
    
    $jsonstring = urlencode($jsonstring);
    curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/salesorders?=&organization_id='.$this->organization_id.'&ignore_auto_number_generation=false',
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
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if($http_code == 201) {
        $responseBody = json_decode($response,true);
         $responseArr = ['success'=>true,'message'=>$responseBody['message'],'data'=>$responseBody['salesorder']];
         return json_encode($responseArr);
    }else{
         $responseArr = ['success'=>false,'message'=>$responseBody['message'],'data'=>$responseBody['salesorder']];
         return json_encode($responseArr);
    }
    
}


public function createInvoice ($salesOrderData,$accessToken) {
    
    $customer_id   = $salesOrderData['customer_id'];
    $shipment_date = $salesOrderData['shipment_date'];
    $date          = $salesOrderData['date'];
    $items         = $salesOrderData['line_items'];
    $customer_name = $salesOrderData['customer_name'];
    $itemsAsString = $this->buildInvoiceItemsAsString($items);
    $curl = curl_init();
    $jsonstring = '{"oauthscope":"ZohoInventory.invoices.CREATE","customer_id":'.$customer_id.',"customer_name":"'.$customer_name.'","date":"'.$date.'","shipment_date":"2015-06-02","line_items":'.$itemsAsString.',"documents":[{"can_send_in_mail":true,"file_name":"sample.pdf","file_type":"pdf","file_size_formatted":"116.8KB","attachment_order":1,"document_id":16115000000096068,"file_size":11957}]}';
    $jsonstring = urlencode($jsonstring);
    curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/invoices?&organization_id='.$this->organization_id.'&ignore_auto_number_generation=false',
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
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $responseBody = json_decode($response,true);
    if( $http_code == 201) {        
        $responseArr  = ['success'=>true,'message'=>$responseBody['message'],'data'=>$responseBody['invoice']];
        return json_encode($responseArr);        
    }else{
        $responseArr = ['success'=>false,'message'=>$responseBody['message'],'data'=>$responseBody['invoice']];
        return json_encode($responseArr);
    }    
    
}

public function createCustomer($accessToken,$customer_phone,$customer_name=null,$customer_address=null,$customer_shipping=null) {

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
        CURLOPT_URL => 'https://inventory.zoho.com/api/v1/contacts?&organization_id='.$this->organization_id.'&ignore_auto_number_generation=true',
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
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $responseBody = json_decode($response,true);
    if( $http_code == 201) {        
        $responseArr  = ['success'=>true,'message'=>$responseBody['message'],'data'=>$responseBody['contact']];
        return json_encode($responseArr);        
    }else{
        $responseArr = ['success'=>false,'message'=>$responseBody['message'],'data'=>$responseBody['contact']];
        return json_encode($responseArr);
    }     
}


public function split_name($name) {
    $name = trim($name);
    $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
    $first_name = trim( preg_replace('#'.preg_quote($last_name,'#').'#', '', $name ) );
    return array($first_name, $last_name);
}
public function buildSalesOrderItemsAsString($items){     
    $arrayAsString = '[';    
    foreach($items as $item){
        $arrayAsString .= '{"item_id":'.$item['item_id'].',"name":"'.$item['name'].'","description":"'.$item['desc'].'","quantity":'.$item['qty'].',"item_total":'.$item['total'].'},';
    }
    $arrayAsString = rtrim($arrayAsString,',');
    $arrayAsString .=']';
    return $arrayAsString;
}

// public function confirmSalesOrder($accessToken,$salesOrderId) {

//     $httpClient1 = new Client([
//         'base_uri' => 'https://inventory.zoho.com/api/v1/salesorders/'.$salesOrderId.'/status/confirmed',
//     ]);
//     $response = $httpClient1->request('Post',
//         '?organization_id='.$this->organization_id.'',[
//             'headers' => [
//                 'Authorization' =>'Zoho-oauthtoken '.$accessToken.''// 'Zoho-oauthtoken 1000.8fee4dd65af86acc34e9b49d1477ac84.6b8a1f05e424a19d7e9c5f89130c7413'
//             ]
//         ]
//     );
//     if($response->getStatusCode() == 200) {
//         return true;
//         // $responseBody = json_decode($response->getbody(),true);
//     }else{
//         return 'there is something goes wrong';
//     }
         
//     // return $responseBody['users'];

// }
public function buildInvoiceItemsAsString($items) {

    $arrayAsString     = '[';
    foreach($items as $item){
        $arrayAsString .='{"item_id":'.$item['item_id'].',"name":"'.$item['name'].'","description":"'.$item['description'].'","rate":'.$item['rate'].',"quantity":'.$item['quantity'].',"salesorder_item_id":'.$item['line_item_id'].',"item_total":""},';
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
        '?organization_id='.$this->organization_id.'&type=AdminUsers',[
            'headers' => [
                'Authorization' =>'Zoho-oauthtoken '.$accessToken.''// 'Zoho-oauthtoken 1000.8fee4dd65af86acc34e9b49d1477ac84.6b8a1f05e424a19d7e9c5f89130c7413'
            ]
        ]
    );
    $http_code = $response->getStatusCode();
    $responseBody = json_decode($response->getbody(),true);
    if($http_code == 200) {        
        $responseArr  = ['success'=>true,'message'=>$responseBody['message'],'data'=>$responseBody['users']];
        return $responseArr;        
    }else{       
        $responseArr  = ['success'=>false,'message'=>$responseBody['message'],'data'=>$responseBody['users']];
        return json_encode($responseArr);
    }
    }


    public function sendEmail($invoice_id,$emails,$accessToken) {//todo pass invoice NO
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
            CURLOPT_URL => 'https://inventory.zoho.com/api/v1/invoices/'.$invoice_id.'/email?organization_id='.$this->organization_id.'',
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
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $responseBody = json_decode($response,true);
        if( $http_code == 200) {            
            $responseArr  = ['success'=>true,'message'=>$responseBody['message']];
            return json_encode($responseArr);        
        }else{
            $responseArr = ['success'=>false,'message'=>$responseBody['message']];
            return json_encode($responseArr);
        }    
    }
    
    
    public function getCustomerById($id,$accessToken) {

        $httpClient1 = new Client([
            'base_uri' => 'https://inventory.zoho.com/api/v1/contacts/'.$id.'?organization_id='.$this->organization_id.'
            ',
        ]);
        $response = $httpClient1->request('GET',
            '?organization_id=743782911&type=AdminUsers',[
                'headers' => [
                    'Authorization' =>'Zoho-oauthtoken '.$accessToken.''// 'Zoho-oauthtoken 1000.8fee4dd65af86acc34e9b49d1477ac84.6b8a1f05e424a19d7e9c5f89130c7413'
                ]
            ]
        );
        $http_code = $response->getStatusCode();
        $responseBody = json_decode($response->getbody(),true);
        if($http_code == 200) {            
            $responseArr  = ['success'=>true,'message'=>$responseBody['message'],'data'=>$responseBody['contact']];
            return $responseArr;        
        }else{            
            $responseArr  = ['success'=>false,'message'=>$responseBody['message'],'data'=>$responseBody['contact']];
            return json_encode($responseArr);
        }      

    }
    



    public function printInvoice($id,$accessToken) {

        $httpClient1 = new Client([
            'base_uri' => 'https://inventory.zoho.com/api/v1/invoices/print',
        ]);
        $response = $httpClient1->request('GET',
            '?organization_id='.$this->organization_id.'&invoice_ids='.$id,[
                'headers' => [
                    'Authorization' =>'Zoho-oauthtoken '.$accessToken.''// 'Zoho-oauthtoken 1000.8fee4dd65af86acc34e9b49d1477ac84.6b8a1f05e424a19d7e9c5f89130c7413'
                ]
            ]
        );
        $http_code = $response->getStatusCode();
        $responseBody = json_decode($response->getbody(),true);
        if($http_code == 200) {            
            $responseArr  = ['success'=>true,'message'=>'invoice has been printed'];
            return $responseArr;        
        }else{            
            $responseArr  = ['success'=>false,'message'=>$responseBody['message']];
            return json_encode($responseArr);
        }
    }

    //transCrop methods 
    public function get_token() {        
        
        $client = new \GuzzleHttp\Client();
        $url   = $this->url.'/authenticateweb';
        $data   = [
                    'username'=>$this->username,
                    'password'=>$this->password
                  ];
        
        $response = $client->post( $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($data)
            ]);
        if($response) {
            $responseBody = json_decode($response->getbody(),true);
        }else{
            return 'there is something goes wrong';
        }
        $this->token = $responseBody['token'];
    }

    public function create_task(Request $request)
    {
       $this->get_token();
       $customer_name  = $request_>get('customer_name');
       $customer_phone = $request->get('customer_phone');
       $shipping_info  = $request->get('shipping_address');
       $shipping_info  = json_decode($shipping_info,true);       
       $street         = $shipping_info['street2'];
       $city           = $shipping_info['city'];
       $state          = $shipping_info['state'];
       $country        = $shipping_info['country'];

        $client = new \GuzzleHttp\Client();
        $url   = $this->url.'/web/task';

        $data   = '[{
            "task_completeafterdate": "2020-09-15",
            "task_completeaftertime": "08:00",
            "task_completebeforetime": "14:00",
            "task_consigneename": "'.$customer_name.'",
            "task_consaddline1": "'.$street.'",
            "task_consdistrict": "'.$state.'",
            "task_conscity": "'.$city.'",
            "task_conscountryname": "'.$contry.'",
            "task_conscontactphone": "'.$customer_phone.'"
            }]';
            
            $header = ['x-access-token' => "$this->token","Content-Type"=>"application/json"];
            
        $response = $client->post( $url, [
                'headers' => $header,
                'body' => $data 
            ]);
            return $response;
        if($response) {
            $responseBody = json_decode($response->getbody(),true);
        }else{
            return 'there is something goes wrong';
        }
        return $responseBody;
    }

    // public function showToken() {
    //     echo csrf_token(); 
  
    //   }

}









