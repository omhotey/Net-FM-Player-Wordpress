<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NET FM Player - Admin preview</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%231d67bb'/%3E%3Cpath d='M25 18v28l22-14z' fill='%23fff'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="/preview/admin.css">
</head>
<body>
    <div class="nfp-admin-preview">
        <aside class="nfp-admin-preview__nav" aria-label="Меню WordPress">
            <a class="nfp-admin-preview__brand" href="/preview/">NET FM</a>
            <nav>
                <a href="#">Консоль</a>
                <a href="#">Записи</a>
                <a href="#">Медиафайлы</a>
                <a href="#">Страницы</a>
                <a href="#">Плагины</a>
                <a class="is-active" href="#" aria-current="page">NET FM Player</a>
                <a href="#">Настройки</a>
            </nav>
            <p>Визуальный прототип. Настройки здесь не сохраняются.</p>
        </aside>

        <main class="nfp-admin-preview__main">
            <header class="nfp-admin-preview__topbar">
                <span>NET FM Player</span>
                <a href="/preview/">Открыть demo-lab</a>
            </header>

            <div class="nfp-admin-preview__content">
                <header class="nfp-admin-preview__hero">
                    <div>
                        <p>ГЛОБАЛЬНАЯ КОНФИГУРАЦИЯ</p>
                        <h1>Настройки радио</h1>
                        <span>Единые значения для shortcode и виджета Elementor. Локальные overrides будут добавлены после утверждения этой структуры.</span>
                    </div>
                    <strong>DEMO</strong>
                </header>

                <div class="nfp-admin-preview__layout">
                    <form class="nfp-admin-preview__form">
                        <section class="nfp-admin-preview__card">
                            <div class="nfp-admin-preview__section-heading"><div><h2>Подключение и metadata</h2><p>Настройте источник данных AzuraCast или укажите готовый Now Playing endpoint.</p></div><span>01</span></div>
                            <div class="nfp-admin-preview__fields">
                                <label>Название станции<input type="text" value="UPWAVE Radio"></label>
                                <label>Station shortcode<input type="text" value="upwaveradio"></label>
                                <label class="is-wide">AzuraCast Base URL<input type="url" value="https://stream.net-fm.ru"></label>
                                <label class="is-wide">Явный Now Playing API URL<input type="url" placeholder="Необязательно"><small>Приоритетнее Base URL и station shortcode.</small></label>
                                <div class="nfp-admin-preview__computed is-wide"><b>Вычисленный metadata URL</b><code>https://stream.net-fm.ru/api/nowplaying/upwaveradio</code></div>
                            </div>
                        </section>

                        <section class="nfp-admin-preview__card">
                            <div class="nfp-admin-preview__section-heading"><div><h2>Потоки и обложка</h2><p>Обычный поток остаётся fallback для всех браузеров. HLS используется только при нативной поддержке.</p></div><span>02</span></div>
                            <div class="nfp-admin-preview__fields">
                                <label>Primary stream URL<input type="url" value="https://stream.net-fm.ru/listen/upwaveradio/stream128"></label>
                                <label>Primary label<input type="text" value="128 kbps"></label>
                                <label>Secondary stream URL<input type="url" value="https://stream.net-fm.ru/listen/upwaveradio/stream320"></label>
                                <label>Secondary label<input type="text" value="320 kbps"></label>
                                <label>HLS Auto stream URL<input type="url" placeholder="Необязательно"></label>
                                <label>Поток по умолчанию<select><option>Primary</option><option>Secondary</option><option>Auto (HLS)</option></select></label>
                                <label class="is-wide">Fallback artwork URL<input type="url" placeholder="https://..."><small>Показывается, когда AzuraCast не передал artwork.</small></label>
                            </div>
                        </section>

                        <section class="nfp-admin-preview__card">
                            <div class="nfp-admin-preview__section-heading"><div><h2>Оформление</h2><p>Сначала выбирается композиция плеера, затем применяются необязательные цветовые overrides.</p></div><span>03</span></div>
                            <div class="nfp-admin-preview__fields">
                                <label>Тема<select><option>Студия</option><option>Винил</option></select></label>
                                <label>Фон плеера<input type="color" value="#16213e"></label>
                                <label>Прозрачность фона<input type="range" min="0" max="100" value="100"></label>
                                <label>Кнопка play<input type="color" value="#ffb703"></label>
                                <label>Громкость<input type="color" value="#219ebc"></label>
                                <label>Цвет текста<input type="color" value="#292118"></label>
                                <label>Фон mute и качества<input type="color" value="#d8caad"></label>
                                <label>Активный поток<input type="color" value="#8f2e19"></label>
                            </div>
                        </section>

                        <section class="nfp-admin-preview__card">
                            <div class="nfp-admin-preview__section-heading"><div><h2>Расположение и тень</h2><p>Выберите композицию, положение плеера на странице и параметры его тени.</p></div><span>04</span></div>
                            <div class="nfp-admin-preview__fields">
                                <label>Версия плеера<select><option>Горизонтальная</option><option>Вертикальная</option></select></label>
                                <label>По горизонтали<select><option>Слева</option><option>По центру</option><option>Справа</option></select></label>
                                <label>По вертикали<select><option>Сверху</option><option>По центру</option><option>Снизу</option></select></label>
                                <label>Отступ от края<input type="range" min="0" max="120" value="48"></label>
                                <label>Цвет тени<input type="color" value="#000000"></label>
                                <label>Прозрачность тени<input type="range" min="0" max="100" value="28"></label>
                                <label>Размытие тени<input type="range" min="0" max="120" value="56"></label>
                                <label>Смещение тени<input type="range" min="-40" max="100" value="24"></label>
                            </div>
                        </section>

                        <section class="nfp-admin-preview__card">
                            <div class="nfp-admin-preview__section-heading"><div><h2>Фон страницы</h2><p>Фон окружения плеера на странице сайта: цвет, прозрачность и изображение.</p></div><span>05</span></div>
                            <div class="nfp-admin-preview__fields">
                                <label>Цвет страницы<input type="color" value="#131416"></label>
                                <label>Прозрачность фона<input type="range" min="0" max="100" value="100"></label>
                                <label class="is-wide">Фоновое изображение<input type="url" placeholder="Выбрать из медиабиблиотеки или вставить URL"><small>В production-версии будет использоваться WordPress Media Library.</small></label>
                            </div>
                        </section>

                        <button class="nfp-admin-preview__save" type="button" disabled>Сохранение появится при переносе в плагин</button>
                    </form>

                    <aside class="nfp-admin-preview__aside">
                        <h2>Интеграция</h2>
                        <p>После внедрения эта конфигурация будет использована shortcode и Elementor.</p>
                        <code>[net_fm_player]</code>
                        <hr>
                        <h2>Порядок работы</h2>
                        <ol><li>Проверяем все поля в demo-lab.</li><li>Утверждаем эту структуру страницы.</li><li>Подключаем Settings API.</li><li>Добавляем те же controls в Elementor.</li></ol>
                    </aside>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
