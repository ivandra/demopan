<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/" rel="dns-prefetch" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8'); ?>">

    <?php if (!empty($yandex_verification)): ?>
        <meta name="yandex-verification" content="<?php echo htmlspecialchars($yandex_verification, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <meta name="robots" content="index, follow">

    <link rel="canonical" href="<?php echo htmlspecialchars($currentUrl ?? ($domain . '/'), ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl ?? ($domain . '/'), ENT_QUOTES, 'UTF-8'); ?>">
	 <meta property="og:image" content="<?php echo $assetsPath; ?>/img/banner_20260119_104714_sol-casino-off.jpg">
    <meta property="og:locale" content="ru_RU">
	<meta property="og:image:width" content="752">
    <meta property="og:image:height" content="534">
    <meta property="og:image:alt" content="Sol casino">
    <meta property="og:image:type" content="image/png">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="stylesheet" href="<?php echo $assetsPath; ?>/styles/templateminimal@main/normalize.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/iizadosywer/templateminimal@main/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Noto+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8'); ?>">


     <!-- Yandex.Metrika counter -->
	<script type="text/javascript" >
	   (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
	   m[i].l=1*new Date();
	   for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
	   k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
	   (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

	   ym(<?php echo $yandex_metrika; ?>, "init", {
			clickmap:true,
			trackLinks:true,
			accurateTrackBounce:true,
			webvisor:true
	   });
	</script>
	<noscript><div><img src="https://mc.yandex.ru/watch/<?php echo $yandex_metrika; ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
	<!-- /Yandex.Metrika counter -->

</head>

<body>
    <div class="center-gradient"></div>
    <header class="header">
        <div class="container">
            <div class="header__content">
                <a href="<?php echo $promolink; ?>">
				
				<img
    src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
    class="header__logo"
    alt="Sol casino">

                <div class="header__buttons">
                    <a href="<?php echo $promolink; ?>" target="_blank" class="main__button">Регистрация</a>
                    <a href="<?php echo $promolink; ?>" target="_blank" class="secondary__button">
                        
                        Зеркало
                    </a>
                    <button class="burger-menu" aria-label="Toggle menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </div>
    </header>
    <nav class="nav">
        <a href="<?php echo $promolink; ?>" class="nav__link">Слоты</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Столы</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Live Casino</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Турниры</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Скачать приложение</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Вход</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Регистрация</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Общие положения</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Политика конфиденциальности</a>
        <a href="<?php echo $promolink; ?>" class="nav__link">Бонусная политика</a>
    </nav>
    <div class="container">
        <section class="hero">
            <div class="hero__content">
                <div class="hero__texts">
                    <h1 class="hero__title"><?=$h1?></h1>
                    <h2 class="hero__subtitle">
                        <span class="hero__subtitle-shadow">375% + 210 FS</span>
                        <span class="hero__subtitle-item">375% + 210 FS</span>
                    </h2>
                    <p class="hero__text">БОНУС ДО 500.000 ₽</p>
                    <br>
                    <a href="<?php echo $promolink; ?>" title="" class="main__button affiliate_button">Получить Бонус</a>
                </div>
                <div class="hero__image"><img src="<?php echo $assetsPath; ?>/img/banner_20260119_104714_sol-casino-off.jpg" alt="Sol casino"></div>
            </div>
        </section>
    </div>
    <div class="container">
       <article>
