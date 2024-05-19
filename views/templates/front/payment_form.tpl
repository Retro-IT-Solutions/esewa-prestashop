{block name='content'}
    <form id="esewa_payment_form" action="{$esewa_url}" method="post">
        <input type="hidden" id="amount" name="amount" value="{$total_products_amount}" required>
        <input type="hidden" id="tax_amount" name="tax_amount" value="{$total_tax_amount}" required>
        <input type="hidden" id="total_amount" name="total_amount" value="{$total_amount}" required>
        <input type="hidden" id="transaction_uuid" name="transaction_uuid" value="{$transaction_unique_id}" required>
        <input type="hidden" id="product_code" name="product_code" value="{$product_code}" required>
        <input type="hidden" id="product_service_charge" name="product_service_charge" value="{$product_service_charge}" required>
        <input type="hidden" id="product_delivery_charge" name="product_delivery_charge" value="{$total_delivery_charge}" required>
        <input type="hidden" id="success_url" name="success_url" value="{$success_url}" required>
        <input type="hidden" id="failure_url" name="failure_url" value="{$failure_url}" required>
        <input type="hidden" id="signed_field_names" name="signed_field_names" value="{$signed_filed_names}" required>
        <input type="hidden" id="signature" name="signature" value="{$signature}" required>
        <input value="submit" type="submit">
    </form>
    <script>
        document.getElementById('esewa_payment_form').submit();
    </script>
{/block}
