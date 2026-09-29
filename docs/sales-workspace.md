# Sales: one customer, one workspace

1. **Sales** lists customers as groups. Search by customer, mobile, business name or invoice. **Open group** opens `/customers/{id}`. Each group shows that customer's invoice numbers and approved totals.
2. **Add Sale** asks for mobile, name, business and date. Existing mobile opens that customer's workspace; a new mobile creates a customer and opens the same workspace. A fresh unfinished invoice is ready there. Reopening while the same user has an unfinished invoice resumes it.
3. At the top of the workspace, the left side shows name, mobile and business. The right side shows approved sales, net received in green and balance due in red. Pending sales/payments do not affect those totals.
4. Completed sales appear in date order, with every product's quantity, rate, discount, reference, amount and invoice totals. Old products are read-only on this page. The admin can select **Edit sale** to open that particular sale in a popup; staff cannot edit old sales. Pagination shows ten completed sales at a time.
5. The new invoice appears **below the previous sales**. Enter products and press the centered round **+** to add another product row. Save to complete it. When there is no unfinished invoice, the centered **+ Add new sale** creates the next invoice for this same customer. Staff-created sales require admin approval; admin-created sales are approved automatically.
6. **Credit / Debit** lives below the sale form. Choose which approved invoice receives the entry, date, amount, method and optional reference. Credit is payment received; debit is money returned. Invoice limits and permissions still apply. Staff payment entries require admin approval. The customer's **Payment History** is at the bottom, once for all their sales, with invoice, date, type, amount and approval status.

## Install

Extract the ZIP into your existing Laravel project root and choose **Replace** when prompted. Run `php artisan migrate` and `php artisan optimize:clear`. Keep your existing `.env`, database and `vendor/` directory. The two ordered Sales migrations run through Laravel only once.
