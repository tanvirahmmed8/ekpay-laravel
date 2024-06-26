<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EkpayPaymentController extends Controller
{

    protected $config = [];


    public function __construct()
    {
        $this->config = config('ekpay');
    }

    public function pay(Request $request)
    {

        $validated = Validator::make($request->all(), [
            'user' => 'required|string|max:255|exists:users,id',
            'order_id' => 'nullable|string|max:255|exists:orders,id',
        ]);

        if ($validated->fails()) {
            echo "Invalid information<br/>";
            print_r($validated->errors());
            die;


            return response()->json(['message' => 'Invalid information', 'errors' => $validated->errors()], 403);
        }

        $user = User::findOrFail($request->user);
        $order = Order::findOrFail($request->order_id);



        if ($order->price < 10) {
            echo "Price must be greater than or equal to 10tk<br/>";
            die;
            return response()->json(['message' => 'Price must be greater than or equal to 10tk'], 403);
        }

        $post_data = array();
        $post_data['user_id'] = $request->user;
        $post_data['order_id'] = $request->order_id;
        $post_data['order_id'] = $order->id;
        $post_data['total_amount'] = $order->price; # You cant not pay less than 10
        $post_data['currency'] = "BDT";
        $post_data['tran_id'] = uniqid(); // tran_id must be unique

        # CUSTOMER INFORMATION


        # CUSTOMER INFORMATION
        $post_data['cus_name'] = $user->name;
        $post_data['cus_email'] = $user->email;
        $post_data['cus_add1'] = '';
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = "";
        $post_data['cus_state'] = "";
        $post_data['cus_postcode'] = "";
        $post_data['cus_country'] = "";
        $post_data['cus_phone'] = $user->phone ?? "+8801795627460";
        $post_data['cus_fax'] = "";

        # SHIPMENT INFORMATION
        $post_data['ship_name'] = "";
        $post_data['ship_add1'] = "";
        $post_data['ship_add2'] = "";
        $post_data['ship_city'] = "";
        $post_data['ship_state'] = "";
        $post_data['ship_postcode'] = "";
        $post_data['ship_phone'] = "";
        $post_data['ship_country'] = "";

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = $order->name;
        $post_data['product_category'] = $order->type;
        $post_data['product_profile'] = "subscription";

        #Before  going to initiate the payment order status need to insert or update as Pending.
        $update_product = DB::table('payments')
            ->where('transaction_id', $post_data['tran_id'])
            ->updateOrInsert([
                'user_id' => $post_data['user_id'],
                'order_id' => $post_data['order_id'],
                'amount' => $post_data['total_amount'],
                'status' => 'pending',
                'transaction_id' => $post_data['tran_id'],
                'currency' => $post_data['currency'],
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now(),
            ]);


        $payment_options = $this->makePayment($post_data);


        if ($payment_options['status'] == 1) {
            $url =  $this->config['apiDomain'] . "?sToken=" . $payment_options['secure_token'] . "&trnsID=" . $payment_options['trx'];
            return redirect($url);
        } else {
            return $payment_options['message'];
        }
    }


    public function makePayment(array $postData)
    {

        // payment pin 3124
        $curl = curl_init();

        $dateTime = Carbon::now(); // Get current date and time

        // Format the date and time
        $formattedDateTime = $dateTime->format('Y-m-d H:i:s') . ' GMT+6';

        $data = [
            "mer_info" => [
                "mer_reg_id" => $this->config['apiCredentials']['user_id'],
                "mer_pas_key" => $this->config['apiCredentials']['store_password']
            ],
            "req_timestamp" => $formattedDateTime,
            "feed_uri" => [
                "c_uri" => $this->config['cancel_url'],
                "f_uri" => $this->config['failed_url'],
                "s_uri" => $this->config['success_url']
            ],
            "cust_info" => [
                "cust_email" => $postData['cus_email'],
                "cust_id" => $postData['user_id'],
                "cust_mail_addr" => "dhaka",
                "cust_mobo_no" => $postData['cus_phone'],
                "cust_name" => $postData['cus_name']
            ],
            "trns_info" => [
                "ord_det" => "order-det",
                "ord_id" => $postData['order_id'],
                "trnx_amt" => $postData['total_amount'],
                "trnx_currency" => $postData['currency'],
                "trnx_id" => $postData['tran_id']
            ],
            "ipn_info" => [
                "ipn_channel" => "1",
                "ipn_email" => $this->config['ipn_email'],
                "ipn_uri" => $this->config['ipn_url']
            ],
            "mac_addr" => $this->config['server_ip']
        ];


        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config['apiDomain'] . '/merchant-api',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>  json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);



        // Check for cURL errors
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            // Handle or log the error message
            echo "cURL Error: " . $error_msg;
            $output = array(
                'status' => 0,
                'message' => $error_msg
            );
        } else {
            $response = json_decode($response);
            if (!empty($response->secure_token)) {
                $output = array(
                    'status' => 1,
                    'secure_token' => $response->secure_token,
                    'trx' => $postData['tran_id']
                );
            } else {
                $msg = "";
                if (!empty($response->responseMessage)) {
                    $msg = $response->responseMessage;
                }
                if (!empty($response->msg_det)) {
                    $msg = $response->msg_det;
                }

                $output = array(
                    'status' => 0,
                    'message' =>  $msg,
                );
            }
        }
        // echo phpinfo();
        curl_close($curl);

        return $output;

        // var_dump($response);
        die();
    }

    public function validation(array $data)
    {



        $curl = curl_init();

        $postData = [
            // "username" =>  $this->config['apiCredentials']['user_id'],
            "trnx_id" => $data['trx_id'],
            "trans_date" => $data['date']
        ];

        curl_setopt_array($curl, array(
            CURLOPT_URL =>  $this->config['apiDomain'] . '/get-status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        $status = 0;
        $message = "";

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            // Handle or log the error message
            echo "cURL Error: " . $error_msg;
            $message = $error_msg;
        } else {
            $response = json_decode($response);
            if ($response->msg_det == "Transaction completed successfully") {
                $status = 1;
                $message = "Transaction completed successfully";
            }
        }

        curl_close($curl);

        return array(
            'status' => $status,
            'message' => $message
        );
    }

    public function success(Request $request)
    {
        // return $request;
        $tran_id = $request->transId;

        $order_details = DB::table('payments')
            ->where('transaction_id', $tran_id)->first();

        if ($order_details->status == 'pending') {

            $data = [
                'trx_id' => $order_details->transaction_id,
                'date' => date('Y-m-d', strtotime($order_details->created_at))
            ];
            $validation = $this->validation($data);

            if ($validation['status'] == 1) {
                DB::table('payments')
                    ->where('transaction_id', $tran_id)
                    ->update(['status' => 'completed']);

                return view('success');
            }
        } else if ($order_details->status == 'processing' || $order_details->status == 'completed') {
            /*
              That means through IPN Order status already updated. Now you can just show the customer that transaction is completed. No need to update database.
              */
            echo "Transaction is successfully Completed:elseif";

            DB::table('orders')
                ->where('id', $order_details->order_id)
                ->update(["payment_status" => "paid"]);

            return view('success');
        } else {
            #That means something wrong happened. You can redirect customer to your product page.
            echo "Invalid Transaction";
        }
    }

    public function fail(Request $request)
    {
        $tran_id = $request->transId;

        $order_details = DB::table('payments')
            ->where('transaction_id', $tran_id)
            ->select('transaction_id', 'status', 'currency', 'amount')->first();

        if ($order_details->status == 'pending') {
            DB::table('payments')
                ->where('transaction_id', $tran_id)
                ->update(['status' => 'failed']);

            echo "Transaction is Falied";

            return view('fail');
        } else if ($order_details->status == 'processing' || $order_details->status == 'completed') {
            echo "Transaction is already Successful";
        } else {
            echo "Transaction is Invalid";
        }
    }

    public function cancel(Request $request)
    {
        $tran_id = $request->transId;


        $order_details = DB::table('payments')
            ->where('transaction_id', $tran_id)
            ->select('transaction_id', 'status', 'currency', 'amount')->first();

        if ($order_details->status == 'pending') {
            DB::table('payments')
                ->where('transaction_id', $tran_id)
                ->update(['status' => 'cancelled']);

            echo "Transaction is Cancel";

            return view('fail');
        } else if ($order_details->status == 'processing' || $order_details->status == 'completed') {
            echo "Transaction is already Successful";
        } else {
            echo "Transaction is Invalid";
        }
    }

    public function ipn(Request $request)
    {
        #Received all the payement information from the gateway
        if ($request->input('transId')) #Check transation id is posted or not.
        {

            $tran_id = $request->input('transId');

            #Check order status in order tabel against the transaction id or order id.
            $order_details = DB::table('payments')
                ->where('transaction_id', $tran_id)
                ->select('transaction_id', 'status', 'currency', 'amount')->first();

            if ($order_details->status == 'pending') {

                /*
              That means IPN worked. Here you need to update order status
              in order table as Processing or Complete.
              Here you can also sent sms or email for successful transaction to customer
              */

                $data = [
                    'trx_id' => $order_details->transaction_id,
                    'date' => date('Y-m-d', strtotime($order_details->created_at))
                ];
                $validation = $this->validation($data);

                if ($validation['status'] == 1) {
                    DB::table('payments')
                        ->where('transaction_id', $tran_id)
                        ->update(['status' => 'completed']);

                    DB::table('orders')
                        ->where('id', $order_details->order_id)
                        ->update(["payment_status" => "paid"]);

                    echo "<br >IPN: Transaction is successfully Completed";
                    echo "<br >IPN: Redirecting...";

                    return view('success');
                }
            } else if ($order_details->status == 'processing' || $order_details->status == 'completed') {

                #That means Order status already updated. No need to udate database.

                echo "<br >IPN: Transaction is already successfully Completed";

                DB::table('orders')
                    ->where('id', $order_details->order_id)
                    ->update(["payment_status" => "paid"]);

                return view('success');
            } else {
                #That means something wrong happened. You can redirect customer to your product page.

                echo "Invalid Transaction";
            }
        } else {
            echo "Invalid Data";
        }
    }
}
