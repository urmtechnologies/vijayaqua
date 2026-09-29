# Sales workspace

## Open and record a sale

1. Open **Sales** from the sidebar. The list has one row per party.
2. Click **Add Sale** and enter the party mobile, name, and sale date. An existing mobile uses the existing customer. The submit creates an unfinished sale and opens its product form.
3. Add as many different products as needed. Enter quantity and a rate with up to two decimal places. Choose an optional staff reference. The logged-in user is recorded automatically.
4. Save the sale. The party account then shows its full sale and payment history. Each saved sale keeps its own invoice number.
5. Record payments against an approved invoice from the party account. Payments remain in the existing `sale_payments` table.

The party account shows approved sales, approved payments, and the outstanding balance. Unfinished sales do not affect stock or the wallet. Staff sales wait for admin approval; staff payments also wait for approval. Pending records remain visible with their status icon.

## Access rules

- An admin sees all parties and sales.
- A staff member with sales access sees only sales they recorded, even if another user sells to the same party. Their displayed wallet totals are limited to those sales.
- Sales create permission lets a staff member complete their own unfinished sale. Editing a completed pending sale still requires edit permission. Approved staff sales cannot be edited by staff.
- A staff member cannot open another person's sale or a party account with no sale belonging to them.

## Data and deployment

Run `php artisan migrate` after deploying. The migration adds an unfinished-sale flag, an optional reference user, decimal amounts, and cached wallet totals on `customers`. It backfills the wallet from existing approved sales and payments. Old invoice discount and vehicle-charge columns are kept so existing invoice amounts remain intact; the new sale form does not ask for them.

The calculation in `app/Support/SaleMoney.php` uses integer paise. For example, 100 cartons at ₹120.10 totals ₹12,010.00. The browser form uses the same approach for its preview.

Run `php artisan test` to check sales, permissions, approvals, stock, reports, and the rest of the application.
