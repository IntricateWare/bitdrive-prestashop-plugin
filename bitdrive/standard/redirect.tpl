<html>
    <head>
        <title>BitDrive Standard Checkout</title>
    </head>
    <body>
        <p>{$redirect_text}<br /><a href="javascript:history.go(-1);">{$cancel_text}</a></p>
        <form action="{$bitdrive_url}" method="post" id="bitdrive_form" class="hidden">
            <input type="hidden" name="bd-cmd" value="pay" />
            <input type="hidden" name="bd-merchant" value="{$merchant_id}" />
            <input type="hidden" name="bd-currency" value="{$currency_module->iso_code}" />
            <input type="hidden" name="bd-amount" value="{$amount}" />
            <input type="hidden" name="bd-memo" value="{$memo}" />
            <input type="hidden" name="bd-invoice" value="{$cart_id}" />
            <input type="hidden" name="bd-success-url" value="{$url}order-confirmation.php?key={$customer->secure_key}&amp;id_cart={$cart_id}&amp;id_module={$bitdrive_id}&amp;slowvalidation" />
            <input type="hidden" name="bd-error-url" value="{$url}" />
        </form>
        <script type="text/javascript">
        {literal}
        document.getElementById('bitdrive_form').submit();
        {/literal}
        </script>
    </body>
</html>