<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
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
    <meta property="og:locale" content="ru_RU">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="stylesheet" href="/style/style.css">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

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
        <header> 
            <div class="container">
                <div class="logo">
                    <a href="/"><img src="/img/logo.svg" alt="Dragon money Casino Online" style="width: 50px;"></a>
                </div>
            </div>
            <div class="container">
                <div class="header-info">
                    <div class="product-info">
                        <h1 id="product-name"><?php echo $h1; ?></h1>
                        <span id="product-bonus">Бонус по промокоду <span style="font-size: 36px;font-weight: 900;">DG-casino</span> </br><b>+200% +100FS!</b></span>
                    </div>
                    <div class="header-button">
                        <a id="header-login" href="<?php echo $promolink; ?>" rel="nofollow">Войти</a>
                        <a id="header-register" href="<?php echo $promolink; ?>" rel="nofollow">Регистрация</a>
                    </div>
                </div>
            </div>
            <div class="container">
                    <div class="benefits">
                        <div class="benefits-blocks">
                            <img src="/img/jackpot.svg" alt="Джекпот">
                            <p><span style="font-size: 120%"><b>€ 572,143</b></span><br><span style="font-size: 80%">Сумма джекпота</span></p>
                        </div>
                        <div class="benefits-blocks">
                            <img src="/img/chat.svg" alt="Поддержка">
                            <p><span style="font-size: 120%"><b>★ ★ ★ ★ ★</b></span><br><span style="font-size: 80%">Поддержка в чате</span></p>
                        </div>
                        <div class="benefits-blocks">
                            <img src="/img/lightning.svg" alt="Вывод средств">
                            <p><span style="font-size: 120%"><b>До 45 минут</b></span><br><span style="font-size: 80%">Время вывода</span></p>
                        </div>
                        <div class="benefits-blocks"><img src="/img/game.svg" alt="Игра">
                            <p><span style="font-size: 120%"><b>Бонус</b></span><br><a href="<?php echo $promolink; ?>" rel="nofollow"><span style="font-size: 80%; color: #d0f426">Забрать бонус →</span></a>
                        </p></div>
                    </div>
            </div>
        </header>
        <div class="container">
        <h2>Популярные игры</h2>
        <div class="slots">
            <div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/wild-hunter.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Sugar Rush 1000</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/emerald-king.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Beheaded</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/rockets.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Ze Zeus</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/wild-walker.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Shining Crown</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/sakura-dragon.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Cyber Bonanza</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/joker-queen.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Rise of Olympus 100</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/big-bad-bison.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Supercharged Clovers</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/savage-buffalo-spirit.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Mummyland Treasures</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/mustang-gold.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">King Midas</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/gangsterz.jpg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Wild Cash X9990</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/irish-thunder.jpg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Baba Yaga Tales</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/lucky-crew.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Sun of Egypt 4</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/survivor.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">20 Extra Crown</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/book-of-gods.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Space Wars</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/northern-sky.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Western Tales</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/legacy-of-doom.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Lucky Streak 27</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div>
        </div>
        <h2>Новые игры</h2>
        <div class="slots">
            <div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/irish-thunder.jpg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Jackpot Hunter</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/golden-fish-tank.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Speed Crash</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/beer-bonanza.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Aztec Treasure Hunt</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/aztec-magic.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Kraken's Hunger</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/cosmo-cats.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Ankh of Anubis</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/gold-vein.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Rooster Mayhem</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/wild-cash.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Donny King</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/royal-high-road.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Ticket To Wild</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/book-of-kingdoms.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Book Of Kingdoms</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/wild-ocean.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Wild Ocean</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/joker-splash.jpg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Joker Splash</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/space-gem.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Space Gem</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/monaco-fever.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Monaco Fever</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/sakura-dragon.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Sakura Dragon</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/master-joker.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Master Joker</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div><div class="slots-blocks">
                <div class="image-container" style="background-image:url('/img/slot/wild-toro.jpeg')"></div>
                <div class="slots-overlay">
                    <span class="slots-text">Wild Toro</span>
                    <a href="<?php echo $promolink; ?>" class="play-button">Играть</a>
                </div>
            </div>
        </div>