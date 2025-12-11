<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<style>
    .wfacp-fsetting-header {
        font-size: 16px;
        line-height: 27px;
        color: #454545;
        padding-right: 15px;
        font-weight: 600;
    }

    .wfacp_fsetting_table_head {
        border-bottom: 1px solid #dedede;
        padding: 12px 25px;
        background-color: #f5f5f5;
        margin-bottom: 25px;
    }

    .wfacp_fsetting_table_head .wfacp_fsetting_table_title {
        font-size: 18px;
        line-height: 27px;
        color: var(--wfacp-text);
        padding-right: 15px;
        display: block;
        text-align: left;
    }

    .wfacp_fsetting_table_head .wfacp_fsetting_table_title a {
        font-size: 12px;
        line-height: 24px;
        position: relative;
        top: 2px;
    }

    .wfacp-scodes-inner-wrap {
        padding: 25px 40px 20px;
        background: #fff;
        -webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        -moz-box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        -ms-box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        -o-box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        border: 1px solid #e5e5e5;
    }

    .wfacp-short-code-wrapper .wfacp-scodes-list-wrap {
        background-color: #fff;
        border: 1px solid #e5e5e5;
    }

    .wfacp-scodes-list-wrap .wfacp-scode-product-head {
        border-bottom: 1px solid #dedede;
        padding: 8px 25px;
        background-color: #f5f5f5;
        font-size: 15px;
        line-height: 24px;
    }

    .wfacp-scodes-list-wrap .wfacp-scodes-products {
        padding: 20px 25px;
    }

    .wfacp-scodes-list-wrap .wfacp-scodes-row {
        display: table;
        width: 100%;
        height: 100%;
        margin-bottom: 10px;
    }

    .wfacp-scodes-row .wfacp-scodes-label {
        display: table-cell;
        vertical-align: middle;
        width: 30%;
        font-size: 13px;
        color: #454545;
    }

    .wfacp-scodes-row .wfacp-scodes-value {
        display: table-cell;
        vertical-align: middle;
        width: 70%;
        border: 1px solid #efefef;
        padding: 8px 20px;
        color: #454545;
        min-height: 35px;
    }

    .wfacp-scodes-row .wfacp-scodes-value-in {
        position: relative;
        padding-right: 60px;
    }

    .wfacp-scodes-row a.wfacp_copy_text {
        position: absolute;
        top: 0;
        right: 0;
        z-index: 2;
        color: #0073aa;
        font-size: 13px;
        padding: 0 5px;
    }

    .wfacp-scodes-notes {
        margin-top: 10px;
    }

    .wfacp-scodes-notes p {
        font-size: 14px;
        line-height: 20px;
        margin: 0 0 10px;
    }

    .wfacp-scodes-notes p:last-child {
        margin-bottom: 0;
    }

    .wfacp-scodes-row.wfacp_vtop .wfacp-scodes-label {
        vertical-align: top;
    }

    .wfacp_exclude_cache_wrap p {
        font-size: 13px;
        line-height: 1.5;
        margin: 0 0 0;
        font-style: italic;
    }

    .wfacp_exclude_cache_wrap {
        margin-top: 20px;
        padding-left: 0;
    }

    .wfacp_embed_fieldset {
        display: none;
    }

    .wfacp_embed_fieldset:first-child {
        display: block;
    }
</style>
<div id="wfacp_design_container">
	<?php include_once __DIR__ . '/design/template-preview.php'; ?>
	<?php include_once __DIR__ . '/design/template-new.php'; ?>
	<?php include_once __DIR__ . '/design/models.php'; ?>
</div>
