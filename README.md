# Trustly Proxy

## Background

This project came to be because the ERP/integration I was in did not
support the type of serialization and signing that Trustly requires
for their API to function. Personally I think it's a poor choice 
from Trustly to work this way. After all, the connection still need
authentication with client certificates, so it's just doing double
work.


## Methodology

Run a proxy of sorts between ERP and Trustly, which takes care of
the signing and serialization. This is not really a great solution,
but it have worked well for a few years now, and the volume is very low.


## Prerequisites

- PHP
  - OpenSSL
  - JSON
- Certificate files in PEM-format:
  - merchant_live_private.pem
  - trustly_live_public.pem


## Installation

Drop proxy.php together with the Trustly's public key and your private
key together on a secure location. Of course there are security concerns
about this here solution. Feel free to improve. The web location should
only be reachable from the designated system.

It's also possible to run it from PHP's built in web server:
`php -S [::]:8922`


## Usage

Any method from https://eu.developers.trustly.com/doc/reference should be usable,
but I've really only used the settlement endpoint in production.

Any data in the request body to the proxy will be added to the request from the
proxy to Trustly. Example below includes credentials, date and currency.

The response is sent both to output and dumped into a file in the `log` folder.

## Example

Use this to fetch settlement details as CSV.

*Request*
```bash
curl \
  -v \
  -X POST http://localhost:8922/proxy.php/ViewAutomaticSettlementDetailsCSV \
  -H 'Content-Type: application/json' \
  -d '{
    "Username" : "example username",
    "Password" : "example password",
    "Currency" : "EUR",
    "SettlementDate" : "2024-03-01"
    }'
```

*Response*

```json
{
    "apiURL": "https://trustly.com/api/1",
    "uuid": "000000000000000000000000000000",
    "method": "ViewAutomaticSettlementDetailsCSV",
    "requestData": {
        "method": "ViewAutomaticSettlementDetailsCSV",
        "version": "1.1",
        "params": {
            "Data": {
                "Username": "example username",
                "Password": "example password",
                "Currency": "EUR",
                "SettlementDate": "2024-03-01",
                "Attributes": {
                    "APIVersion": "1.2"
                }
            },
            "UUID": "000000000000000000000000000000",
            "Signature": "C34ZqJvJSLZx5Is3IsWOzZOoer8DGebIfbf83sHGgu2Qv7YIICXkpGm9wfXfIW8mV2RyvhvtEY65M5PQFNIwbipMn9yvCIdoohtO+aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaK+e90mjaY5UiAnFKA1oCZFA7uQn4dIdYzDc2t4a+6iwxefthtJOaV3ZNkz7ej8cqK4aaaaaaaaaaaaaaaaaaaaaaanVK0wV5ftbGugBxwEhzgCNdPXi/RrL0n9drviGHRN6IyuremGIAyCg5FfwaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaDkHcAIZVcwsOxdc6yaOw=="
        }
    },
    "requestSerial": "ViewAutomaticSettlementDetailsCSV000000000000000000000000000000AttributesAPIVersion1.2CurrencyEURPasswordbexamplepasswordSettlementDate2024-03-01Usernameexampleusername",
    "responseData": {
        "version": "1.1",
        "result": {
            "data": {
                "view_automatic_settlement_details": "datestamp,accountname,currency,amount,total,orderid,ordertype,messageid,username,fxpaymentamount,fxpaymentcurrency,settlementbankwithdrawalid,externalreference\n\"2024-03-25 07:57:07.252702+00\",SUSPENSE_ACCOUNT_CLIENT_FUNDS_SWEDEN_SWED,SEK,948.01,4148.01,12345678,Deposit,22222222-bb83-442f-bfab-22222222,example username,,,1234998358,1001034006\n\"2024-03-25 11:54:26.081663+00\",SUSPENSE_ACCOUNT_CLIENT_FUNDS_SWEDEN_SWED_32,SEK,3200.00,4148.01,12345678,Deposit,22222222-650f-4692-a074-22222222,example username,,,1234998358,99999999\n"
            },
            "uuid": "000000000000000000000000000000",
            "signature": "FRQKSNaaaaaaaaaaaaaaaP7IKewgmU8l7oelIu0Wisng/xUtJ09vgKYnDHx0tgqj66RonXladSnKdXN0p0v2snA/LIPeUTxEtjvioadRLgTJ7I2MiaaaaaaaaaaaaaaaaaaaaaakYdnvqoByNWTuAepEew3PemQ+GXkyr5QxwDW1HcPPYFt0pOKphBAaaaaaaaaaaaaaaaaaaaaaaaa4SvZVhnwoCsWWPda+iEf7o+6O9EZVPf9r48315T5dPfBZaaaaaaaaaaaaaaaaaaaaaaaaajsb9Fgg23E1nTKw66ssNGVTv1mg==",
            "method": "ViewAutomaticSettlementDetailsCSV"
        }
    }
}
```


## License

Apache License. No guarantees, no responsibility. Use at own risk.
This is not supported by, endorsed by or affiliated with Trustly.
