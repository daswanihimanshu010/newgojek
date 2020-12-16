<?php
  
namespace App\Jobs;
   
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Foundation\Bus\DispatchesJobs;

use App\Helpers\Helper;
use Mail;
   
class SendEmailJob implements ShouldQueue
{
    use  InteractsWithQueue, Queueable, SerializesModels;
  
    protected $details;
  
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
    }
   
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle($error)
    {
        print_r($error);exit;
        // $email = new SendEmailTest();
        // Mail::to($this->details['email'])->send($email);
            $toEmail = "dilip@appoets.com";
             //  SEND OTP TO MAIL
             $subject='500 Error in GOX';
             $templateFile='mails/errormail';
             $data=['body'=>"500 error :".$error];

             $result= Helper::send_emails_job($templateFile,$toEmail,$subject, $data); 
      
    }
}