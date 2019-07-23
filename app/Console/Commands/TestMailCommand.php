<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestMail;

class TestMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:mail
        {email : Email addres to send test message to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test mail to an email address';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info("Sending test mail to [$email]");
        Mail::to($email)->send(new TestMail);
        $this->info("Mail was successfully queued.");
    }
}
