# Security reporting

Do not put exploit details, shop URLs, customer data or credentials in a public
issue. Use an existing private contact with the SKYYWARE maintainer to arrange
a confidential report. No response-time or support-service guarantee is made.

Include the Starcode, Shopware and PHP versions, the affected form route and a
minimal synthetic reproduction. Remove mail addresses, tokens and production
payloads. Test only on systems you are authorized to assess.

Starcode is a proof-of-work gate, not user authentication, authorization, CSRF
protection or a replacement for request validation. Keep those protections and
your host's abuse controls enabled. Do not add payment, webhook, admin or
unrelated business endpoints to its form allowlist.

The initial 0.1 release is intended for integration testing. It has not received
an independent security audit. Validate updates in an isolated shop before
deployment and keep the dependency versions under review.
