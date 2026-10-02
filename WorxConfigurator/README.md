# Worx Configurator

The configurator lists cloud devices whose reports contain a complete, supported protocol-0 weekly schedule. This schedule-format check is not a claim that every device using protocol 0 is supported.

New mower instances are fail-closed by default. AllowedProductID starts at 0, which disables creation. The form shows the numeric product_id values of schedule-compatible candidates locally in IP-Symcon. Enter the ID of the mower validated for this installation; only the exact matching device appears in the Configurator table with a create action. Missing, non-integer, or changed product IDs remain blocked.

The product ID is stored as a local IP-Symcon property. It is not hard-coded in this repository, added to diagnostic exports, or sent to another service. Do not copy device identifiers or full cloud responses into public issue reports. The Worx cloud payload field has been observed as an integer in the current installation; if Worx changes that field or its type, the gate fails closed.

Existing mower instances are not deleted or modified by changing this configurator property.
