<?php
/*
 * Endpoint for Github Webhook URLs
 *
 */
// script errors will be send to this email:
$error_mail = "..";
$error_from = "..";

class Handler
{
    private $config;
    private $signature;
    private $event;
    private $delivery;
    private $payload;

    public function __construct($config_file)
    {
        if (!file_exists($config_file)) {
            throw new Exception("Can't find json config file ".$config_file);
        }
        $this->config = json_decode(file_get_contents($config_file), true);
    }

    public function validate()
    {
        $signature = @$_SERVER['HTTP_X_HUB_SIGNATURE'];
        $event = @$_SERVER['HTTP_X_GITHUB_EVENT'];
        $delivery = @$_SERVER['HTTP_X_GITHUB_DELIVERY'];
        if (!isset($signature, $event, $delivery)) {
            return false;
        }
        $payload = file_get_contents('php://input');
        // Check if the payload is json or urlencoded.
        if (strpos($payload, 'payload=') === 0) {
            $payload = substr(urldecode($payload), 8);
        }
        if (!$this->validateSignature($signature, $payload)) {
            throw new Exception("This does not appear to be a valid requests from Github.\n");
        }
        $this->signature = $signature;
        $this->event = $event;
        $this->delivery = $delivery;
        $this->payload = json_decode($payload,true);
        return true;
    }

    private function validateSignature($gitHubSignatureHeader, $payload)
    {
        list ($algo, $gitHubSignature) = explode("=", $gitHubSignatureHeader);
        if ($algo !== 'sha1') {
            // see https://developer.github.com/webhooks/securing/
            return false;
        }
        $payloadHash = hash_hmac($algo, $payload, $this->config['secret']);
        return ($payloadHash === $gitHubSignature);
    }

    public function run() {
        // check if the request comes from github server and is valid
        if (!$this->validate()) {
            return false;
        }
        if (isset($this->config['email'])) {
            $headers = 'From: '.$this->config['email']['from']."\r\n";
            $headers .= 'CC: ' . $this->payload->pusher->email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        }
        // evaluate the endpoints
        foreach ($this->config['endpoints'] as $endpoint)
        {
            // check if the push came from the right repository and branch
            if ( $this->payload['repository']['full_name'] == $endpoint['repository'] &&
                 $this->payload['ref'] == 'refs/heads/'.$endpoint['branch'] &&
                 $this->event == $endpoint['event']
               ) {
                // execute update script, and record its output
                ob_flush();
                ob_start();
                passthru(sprintf("%s > %s 2>&1 & echo $!", $endpoint['run'], $endpoint['log']), $err);
                $output = ob_get_contents();
                // prepare and send the notification email
                if (isset($headers)) {
                    // send mail to someone, and the github user who pushed the commit
                    $body = '<p>The Github user <a href="'. $this->payload['sender']['html_url'] 
                        . '">@' . $this->payload['sender']['login'] . '</a>'
                        . ' has triggered (' . $this->event .') to <a href="' . $this->payload['repository']['html_url'] 
                        . '">' . $this->payload['repository']['full_name'] . '</a>'
                        . ' and consequently, ' . $endpoint['action']
                        . '.</p>';
                    if ( array_key_exists('head_commit',$this->payload) ) {
                        $body .= '<p>Trigger of the event: '. $this->payload['head_commit']['message'];
                        $body .= ' on ' . $this->payload['head_commit']['timestamp'];
                        $body .= ' <a href="' . $this->payload['head_commit']['url'] . '">url</a></p>';
                    }
                    if ( array_key_exists('commits',$this->payload) ) {
                        $body .= '<p>Here\'s a brief list of what has been changed:</p>';
                        $body .= '<ul>';
                        foreach ($this->payload['commits'] as $commit) {
                            $body .= '<li>'.$commit['message'].'<br />';
                            $body .= '<small style="color:#999">added: <b>'.count($commit['added'])
                                .'</b> &nbsp; modified: <b>'.count($commit['modified'])
                                .'</b> &nbsp; removed: <b>'.count($commit['removed'])
                                .'</b> &nbsp; <a href="' . $commit['url']
                                . '">read more</a></small></li>';
                        }
                        $body .= '</ul>';
                    }
                    $body .= sprintf(
                        "<p>Command (%s) started with pid=%s and <a href=\"%s\">logfile</a>.</p>",
                        $endpoint['run'], $output, "https://$_SERVER[HTTP_HOST]/".$endpoint['log']);
                    if (!empty($err)) {
                        $body .= sprintf('<p>Returned error code <strong>%s</strong>!</p>', $err);
                    }
                    $body .= '<p>Cheers, <br/>Github Webhook Endpoint</p>';
                    mail($this->config['email']['to'], $endpoint['repository']." : ".$endpoint['action'], $body, $headers);
                }
                return true;
            }
        } 
        //throw new Exception("A valid hook from Github has been delivered but it isn't an endpoint in your config.\n");
    }
}
try {
    $handler = new Handler('../config.json');
    if (!$handler->run()) {
        echo basename($_SERVER['PHP_SELF']) . " works fine.";
    }
} catch ( Exception $e ) {
    $msg = $e->getMessage();
    mail($error_mail, $msg, ''.$e, "From: $error_from\r\n");
}
