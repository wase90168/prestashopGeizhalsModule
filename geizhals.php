<?php
header("Content-type: text/html; charset=utf-8");

// ------------------------------------------------------------------------------------------------------
// -------------------------------------------- CONFIG --------------------------------------------------
// ------------------------------------------------------------------------------------------------------


//Uncomment these three lines if you want the errors to be displayed.
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

//Table-prefix of the prestashop tables; e.g. ps
$shop_dbPrefix = "<Table-Prefix>";

// Name of the database schema; e.g. prestashop
$shop_dbSchemaName = "<dbSchema name>";

// Shop domain; e.g. http://myshop.at/ or https://myshop.at/
$shop_domain = "<SHOP URL (http or https)>";

// Specify products from a country or leave empty for all products; e.g. "AT", "DE" or ""
$iso_code = "<ISO_CODE>";

// Path to the db-config file of prestashop.
require_once('../config/settings.inc.php');

// ------------------------------------------------------------------------------------------------------
// ------------------------------------------ END OF CONFIG ---------------------------------------------
// ------------------------------------------------------------------------------------------------------

$iso_code_select = 'AND c.iso_code = "' . $iso_code . '"';

function getShippingCost($product_weight, $output_shipping){
    $shipping_array = array();
    while ($prices = mysqli_fetch_array($output_shipping, MYSQL_ASSOC))
    {
        $output_price = -1;
        if(intval($prices["is_free"]) == 1 and $product_weight <= floatval($prices["max_weight"]) and is_null($prices["delimiter1"]) and is_null($prices["delimiter2"]))
        {
            $output_price = 0;
        } elseif(intval($prices["is_free"]) == 0 and !is_null($prices["delimiter1"]) and !is_null($prices["delimiter2"])){
            if($product_weight >= floatval($prices["delimiter1"]) and $product_weight <= floatval($prices["delimiter2"]) and (floatval($prices["max_weight"]) == 0 or $product_weight <= floatval($prices["max_weight"]))){
                $output_price = floatval($prices["price"]);
            }
        }
        if($output_price != -1)
        {
            $shipping_array[] = $output_price;
        }
    }
    if (empty($shipping_array)){
        return NULL;
    } else {
        return replaceDot(min($shipping_array));
    }
}

function calcEndPrice($price, $reducted_price, $reduction){
    if(is_null($reducted_price) and is_null($reduction)){
        return replaceDot($price);
    } else if(intval($reducted_price) >= 1){
        return floatval($reducted_price) - floatval($reduction);
    } else {
        if(intval($reducted_price) == -1 or is_null($reducted_price)){
            return replaceDot(floatval($price) - floatval($reduction));
        } else {
            return replaceDot(floatval($reducted_price) - floatval($reduction));
        }
    }
}

function concatVars($var1, $var2){
    return (string)$var1 . " " . (string)$var2;
}

function getDeeplink($product_id, $prodRewrite, $catRewrite, $ean, $domain){
    return $domain . $catRewrite . "/" . $product_id . "-" . $prodRewrite . ($ean == "" ? "" : "-") . $ean . ".html";
}

function replaceDot($value){
    return str_replace(".",",",(string)$value);
}

function addTax($price, $tax){
    return round(floatval($price)* (1 + (floatval($tax) / 100)), 2);
}

function getRightAvailability($textAvailable, $textUnavailable, $stock){
    if(intval($stock) > 0){
        return $textAvailable;
    }
    else{
        return $textUnavailable;
    }
}

$parts = explode(":",_DB_SERVER_);
$db_link = mysqli_connect (
                     $parts[0],
                     _DB_USER_,
                     _DB_PASSWD_,
                     _DB_NAME_
                    );

mysqli_set_charset($db_link,"utf8");

$sql = 'SELECT pr.id_product, pl.name, pr.reference, pr.price AS orig_price, sp.price AS reduc_price, sp.reduction, pl.link_rewrite AS prodRewrite, pr.weight, pr.ean13, t.rate AS taxRate, pl.available_now, pl.available_later, sa.quantity, cl.link_rewrite AS catRewrite FROM ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_product pr
JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_stock_available sa ON pr.id_product = sa.id_product
JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_product_lang pl ON pr.id_product = pl.id_product
LEFT JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_specific_price sp ON sp.id_product = pr.id_product
JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_tax_rules_group tg ON tg.id_tax_rules_group = pr.id_tax_rules_group
JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_tax_rule tr ON tr.id_tax_rules_group = tg.id_tax_rules_group
JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_country c ON tr.id_country = c.id_country
JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_tax t ON tr.id_tax = t.id_tax
JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_category_lang cl ON pr.id_category_default = cl.id_category
WHERE pr.active = 1 AND pr.available_for_order = 1 AND (sp.id_cart = 0 OR isnull(sp.id_cart)) ' . ($iso_code == "" ? "" : $iso_code_select) . '
ORDER BY pr.id_product;';

$sql_shipping = 'SELECT is_free, max_weight, price, delimiter1, delimiter2 FROM ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_carrier c
    JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_carrier_lang cl ON c.id_carrier = cl.id_carrier
    LEFT JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_delivery d ON d.id_carrier = c.id_carrier
    LEFT JOIN ' . $shop_dbSchemaName . '.' . $shop_dbPrefix . '_range_weight rw ON rw.id_range_weight = d.id_range_weight
    WHERE deleted = 0 AND name NOT LIKE "%bholung%";';

$output = mysqli_query($db_link, $sql);
$output_shipping = mysqli_query($db_link, $sql_shipping);

$file = "geizhals.csv";
$firstLine = "Produktbezeichnung" . ";" . "Herst. Nr." . ";" . "Preis" . ";" . "Deeplink" . ";" . "Verfügbarkeit" . ";" . "Versand AT" . ";" . "EAN" . "\n";
$write_line = mb_convert_encoding($firstLine, 'UTF-8');
file_put_contents($file, $firstLine);
while ($line = mysqli_fetch_array($output, MYSQL_ASSOC))
{
    $write_line = trim(concatVars($line["name"], $line["reference"])) . ";" . trim($line["reference"]) . ";" . calcEndPrice(addTax($line["orig_price"], $line["taxRate"]), $line["reduc_price"], $line["reduction"]) . ";" . getDeeplink($line["id_product"], $line["prodRewrite"], $line["catRewrite"], $line["ean13"], $shop_domain) . ";" . getRightAvailability($line["available_now"], $line["available_later"], $line["quantity"]) . ";" . getShippingCost($line["weight"], $output_shipping) . ";" . $line["ean13"] . "\n";
    $write_line = mb_convert_encoding($write_line, 'UTF-8');
    file_put_contents($file, $write_line, FILE_APPEND);
    mysqli_data_seek($output_shipping, 0);
}
mysqli_free_result($output);
mysqli_free_result($output_shipping);
mysqli_close($db_link);
?>
