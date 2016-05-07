# Command line interface for Raiffeisen online

This project allows to do basic actions on the Bulgarian Raiffeissen Online
banking software through the command line. I wrote this because I have RSI and
cannot use a mouse, and it is very difficult for me to use the website. It will
also be useful for people that have a visual disability, or of you simply
prefer to use the command line.

For the moment there is only support for creating basic transactions in leva.

Raiffeisen does not offer an API, so this works by emulating an able mouse
user clicking through the website.

## Requirements

This requires PHP 5.6 or higher, and either Selenium 2 or PhantomJS.

## Installation

First install the dependencies:
```
$ composer install
```

Then create a configuration file `config/config.yml` and in here store your
user name and password, and the browser driver you want to use (either
"selenium2" or "phantomjs"):

```
credentials:
  username: 'my username'
  password: 'my password'

mink:
  default_session: 'selenium2'
```

## Usage

First you should set up your account names for your individual and corporate
accounts with the 'account:add' command. Use the "short name" for the account,
this looks similar to '1234567890 BGN'.

```
$ ./raiffcli account:add
```

Then you can create a transaction with the 'transfer:in-leva' command. It will
ask you for the details.

```
$ ./raiffcli transfer:in-leva
```

Note that you will still need to log in to the site manually to sign and send
the transactions.