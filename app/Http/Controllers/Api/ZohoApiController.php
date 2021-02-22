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

    public function createSaleOrder() {
        
        $access_code   = $this->generateAccessToken();
        $authorization = 'Zoho-oauthtoken '.$access_code; 
        $data          = '\"JSONString \" =>    \"oauthscope\": \"ZohoInventory.salesorders.CREATE\",    \"customer_id\": 2540554000000074003,    \"salesorder_number\": \"SO-002099989203\",   \"date\": \"2015-05-28\",    \"shipment_date\": \"2015-06-02\",    \"custom_fields\": [ {}    ],    \"reference_number\": \"REF-S-00003\",   \"line_items\": [       {            \"item_id\": 2540554000000073331,           \"name\": \"Laptop-white/15inch/dell\",            \"description\": \"Just a sample description.\",           \"rate\": 122,\n            \"quantity\": 2,            \"unit\": \"qty\",                       \"item_total\": 244                               }    ],        \"documents\": [{           \"can_send_in_mail\": true,           \"file_name\": \"sample.pdf\",            \"file_type\": \"pdf\",            \"file_size_formatted\": \"116.8 KB\",            \"attachment_order\": 1,            \"document_id\": 16115000000096068,            \"file_size\": 11957        }    ]    }';
        
       
       
      
        //$data1 = json_encode($data);
            //dd($data);
            $header = [                    
                'Authorization'=>$authorization,
                'Content-Type' =>'application/x-www-form-urlencoded;charset=UTF-8',
               ];    
        $httpClient1 = new Client([
            'base_uri' => env('ZOHO_INV_API_URL').'/salesorders',            
        ]);        
        try {
        $response = $httpClient1->request('POST',
            '?organization_id='.$this->organization_id.'&ignore_auto_number_generation=false',[
                'body'=> urlencode(json_encode($data)),
                'headers'=>$header,
                ]
               ); 
            } catch (GuzzleHttp\Exception\ClientException $e) {
                $response = array(
                    'success' => false,
                    'errors' => json_decode($e->getResponse()->getBody(true)),
                );
            }              
                
                if($response->getStatusCode() >= 200) return 'Sale Order Created';
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
}