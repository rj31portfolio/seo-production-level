# Client billing

Each client subscription creates a client invoice. Renewal creates an additional invoice; it never automatically marks money received. The current UI records externally received manual bank-transfer/cash/cheque/other payments with a unique per-agency reference, amount and received timestamp.

Payments lock the invoice row and cannot exceed its remaining balance. Partial payments set partially_paid; a zero remaining balance sets paid. Amounts/currency on existing invoices remain unchanged if the service plan changes later. Invoice detail includes client information, service description, amount, balance, status and payment history; browser print is available.

This is an initial billing record module. Tax, line items, credit notes/refunds, signed exports, payment collection/gateways, proration, financial analytics, SaaS billing and accounting reconciliation are not implemented. Do not treat manually entered payments as verified gateway settlements. No revenue figures are invented.
