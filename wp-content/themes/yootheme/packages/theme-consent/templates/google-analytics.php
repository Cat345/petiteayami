<script>
/* Google analytics (gtag.js) */
window.dataLayer = window.dataLayer || [];

function gtag(){ dataLayer.push(arguments); }
function gtagConsent(consent) {
    const ads = consent?.hasConsent('marketing.google_ads') ? 'granted' : 'denied';
    const analytics = consent?.hasConsent('statistics.google_analytics') ? 'granted' : 'denied';
    return { ad_storage: ads, ad_user_data: ads, ad_personalization: ads, analytics_storage: analytics };
}

gtag('js', new Date());
gtag('config', '<?= $script['api_key'] ?? '' ?>');
gtag('consent', 'default', gtagConsent());

document.addEventListener('yootheme:consent.init', ({detail}) => gtag('consent', 'update', gtagConsent(detail)));
document.addEventListener('yootheme:consent.change', ({detail}) => gtag('consent', 'update', gtagConsent(detail)));
</script>
<script data-category="statistics.google_analytics marketing.google_ads" src="https://www.googletagmanager.com/gtag/js?id=<?= $script['api_key'] ?? '' ?>" async></script>
