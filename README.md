# Github-webhook

A lightweight and simple php script to validate and handle Github's webhooks centrally for multiple projects. Everything except your configuration (secret and repositories to handle) is included in a single file so you can put the script anywhere you want.

## Setup

1. Place `github-webhook.php` in the public domain of your server, for example, `https://yourdomain/github-webhook.php`

1. Repository / Webhooks / Manage webhook

   * Payload URL = `https://yourdomain/github-webhook.php`
   * Content type = `application/json`
   * Secret = `your secret`
   * If you push everything the changes will be added to the notification email.


1. Create a configuration file, for example, `config.json`, outside your pubic html folder

## Json configuration file

```
{
    "secret": "...",
    "email": {
        "from": "github-webhook@your_domain",
        "to": "your_email"
    },
    "endpoints": [
        {
            "repository": "user/repository",
            "branch": "master",
            "event": "push",
            "action": "a new version has been deployed",
            "run": "/home/bin/deploy.sh 2>&1"
        }
    ]
}
```

That's it.
