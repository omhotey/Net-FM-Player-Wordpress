# Roadmap: NET FM Player for AzuraCast

> Актуальный план первого производственного выпуска и приоритет нескольких станций: [PRODUCTION-PLAN.md](PRODUCTION-PLAN.md). Этапы ниже сохранены для истории и развития продукта.

- **Версия документа:** 1.1
- **Дата:** 8 августа 2026
- **Связанный документ:** [PRD.md](./PRD.md)
- **Принцип:** сначала стабильное ядро одной станции, затем расширение listener-facing функций AzuraCast

## 1. Исходное состояние

Зафиксированный рабочий baseline содержит:

- shortcode `[net_fm_player]`;
- настройки `net_fm_player_options`;
- Elementor widget `net-fm-player`;
- темы Studio/Square и завершённую Vinyl-композицию;
- основной и дополнительный direct stream;
- Now Playing polling;
- title, artist, artwork, listeners и online status;
- play/pause, mute, volume и stream switcher;
- delayed buffering, ограниченный reconnect и direct-stream fallback;
- опциональный Auto (HLS) поток для браузеров с нативной HLS-поддержкой;
- предзагрузку `playing_next` artwork и переход metadata в конце трека;
- адаптивную Vinyl-версию без горизонтального overflow, с увеличенными mobile-отступами между основными блоками;
- station label в шапке на desktop и mobile;
- скрытое меню выбора станции, пока в конфигурации доступна только одна станция;
- локальная preview-страница.

Metadata API считается UTF-8 JSON контрактом. Ответы, уже декодированные браузером с символом замены Unicode (`U+FFFD`), не перекодируются: player показывает fallback для повреждённых title/artist.

Следующий продуктовый приоритет после baseline — несколько станций с активным меню выбора. До появления двух и более станций кнопка выбора не выводится, чтобы не обещать пользователю недоступное действие.

## 2. Общие правила реализации

1. Публичное название — **NET FM Player for AzuraCast**.
2. Сохраняются контракты `net_fm_player`, `net-fm-player`, `.nfp` и `window.NetFmPlayer`.
3. Production-плагин остаётся dependency-free.
4. Один renderer обслуживает shortcode, Gutenberg и Elementor.
5. Direct stream работает независимо от AzuraCast metadata.
6. Каждая новая настройка проходит полный путь:
   - default;
   - sanitization;
   - storage;
   - shortcode/local overrides;
   - Elementor/Gutenberg control;
   - frontend JSON;
   - JavaScript consumption;
   - documentation and tests.
7. Каждый этап заканчивается проверкой, а не только внесением изменений.
8. Новые крупные функции не добавляются, пока предыдущий этап не прошёл Definition of Done.

## 3. Этап 0 — зафиксировать baseline

**Цель:** получить понятную и проверяемую исходную версию до архитектурных изменений.

### Задачи

- [x] Просмотреть текущий diff основных файлов.
- [x] Отделить завершённые изменения Vinyl/HLS/metadata от экспериментов.
- [x] Проверить preview fixtures и исключить локальные MP4 из Git.
- [x] Зафиксировать публичный `stream_hls_url` в shortcode, settings, Elementor и frontend config.
- [x] Зафиксировать структуру `data-config` с `primary`, `secondary` и `hls` streams.
- [x] Исправить критические проблемы baseline без архитектурного рефакторинга.
- [x] Создать отдельный baseline commit по явному запросу пользователя.

### Проверка

```powershell
php -l net-fm-player.php
php -l includes/class-net-fm-elementor-widget.php
git diff --check
```

Ручная матрица:

- shortcode с глобальными настройками;
- shortcode с локальными overrides;
- Elementor global/local;
- direct stream без API;
- AzuraCast success/error;
- основной и дополнительный поток;
- desktop/mobile.

### Definition of Done

- [x] текущая версия воспроизводима;
- [x] рабочее поведение тем задокументировано;
- [x] локальные MP4 не входят в release scope;
- [x] последующий рефакторинг можно сравнить с baseline.

---

## 4. Этап 1 — metadata и WordPress.org foundation

**Цель:** привести идентичность и базовые метаданные плагина к утверждённому продукту.

### Задачи

- [ ] Обновить display name на `NET FM Player for AzuraCast`.
- [ ] Установить Author `omhotey` и Author URI `https://vibeservice.ru/`.
- [ ] Добавить `Requires at least`, `Requires PHP`, `License`, `License URI`, `Text Domain`, `Domain Path`.
- [ ] Принять минимумы WordPress 6.5 и PHP 8.2 либо скорректировать их после тестов.
- [ ] Добавить загрузку переводов.
- [ ] Подготовить English source strings и русский перевод.
- [ ] Убрать жёстко заданные русские строки из JavaScript.
- [ ] Задокументировать внешний сервис AzuraCast и direct stream.
- [ ] Не использовать официальный логотип AzuraCast как собственный бренд без разрешения.

### Definition of Done

- plugin header соответствует PRD;
- text domain единообразен;
- PHP и JavaScript интерфейс переводим;
- технические контракты не сломаны.

---

## 5. Этап 2 — модульное ядро без изменения поведения

**Цель:** разделить текущий монолит перед добавлением новых возможностей.

### Целевая структура

```text
net-fm-player.php
includes/
  class-net-fm-player-plugin.php
  class-net-fm-player-assets.php
  class-net-fm-player-settings.php
  class-net-fm-player-config.php
  class-net-fm-player-renderer.php
  class-net-fm-player-shortcode.php
  class-net-fm-player-azuracast-client.php
  class-net-fm-player-diagnostics.php
  integrations/
    class-net-fm-player-elementor.php
    class-net-fm-player-gutenberg.php
assets/
  net-fm-player.js
  net-fm-player.css
  net-fm-player-admin.js
  net-fm-player-admin.css
blocks/
  player/
    block.json
    render.php
languages/
```

Структура может вводиться постепенно; нельзя дробить файлы без практической необходимости.

### Задачи

- [ ] Оставить bootstrap минимальным.
- [ ] Выделить defaults и схему настроек.
- [ ] Выделить sanitization.
- [ ] Выделить config builder.
- [ ] Выделить renderer.
- [ ] Перевести shortcode на renderer.
- [ ] Перевести Elementor на renderer без собственной разметки.
- [ ] Устранить неоднозначность `stream_primary` и `stream_primary_url`.
- [ ] Добавить номер схемы данных и миграционный механизм.
- [ ] Сохранить asset handles и Elementor widget name.

### Definition of Done

- frontend визуально и функционально совпадает с baseline;
- shortcode и Elementor не дублируют бизнес-логику;
- defaults/sanitization/config находятся в явных слоях;
- PHP lint и ручная матрица успешны.

---

## 6. Этап 3 — подключение одной станции AzuraCast

**Цель:** перейти от ручного набора URL к управляемому подключению.

### Задачи

- [ ] Добавить мастер подключения.
- [ ] Проверять base URL и station shortcode через WordPress HTTP API.
- [ ] Получать публичные данные станции.
- [ ] Автоматически извлекать `listen_url`.
- [ ] Извлекать `mounts[]`, format и bitrate.
- [ ] Определять default mount.
- [ ] Сохранять ручной основной direct stream.
- [ ] Сохранять резервный direct stream.
- [ ] Показывать результат проверки без технических секретов.
- [ ] Не требовать API key.
- [ ] Не блокировать сохранение direct stream при недоступном metadata API.

### Приоритет потоков

1. Явно выбранный администратором mount.
2. AzuraCast default MP3/AAC mount.
3. `station.listen_url`.
4. Ручной основной direct stream.
5. Резервный direct stream.

Точный порядок должен учитывать намерение администратора и быть покрыт сценариями миграции.

### Definition of Done

- публичная станция подключается по двум основным полям;
- direct-only конфигурация остаётся рабочей;
- selector качества использует реальные mount points;
- ошибка подключения объяснима в Diagnostics.

---

## 7. Этап 4 — жизненный цикл frontend player

**Цель:** обеспечить устойчивое воспроизведение и корректное уничтожение экземпляров.

### Задачи

- [ ] Формализовать состояния `idle`, `loading`, `playing`, `paused`, `reconnecting`, `offline`, `error`.
- [ ] Обрабатывать Promise от `audio.play()`.
- [ ] Реализовать ограниченный exponential reconnect.
- [ ] Остановить reconnect после явной паузы пользователя.
- [ ] Сохранять volume, mute и stream key в namespaced storage.
- [ ] При запуске одного плеера приостанавливать другие.
- [ ] Добавить `destroy()` для timers, listeners, fetch и animation frames.
- [ ] Обрабатывать удаление/перерисовку Elementor widget.
- [ ] Приостанавливать polling в скрытой вкладке.
- [ ] Координировать polling нескольких экземпляров одной станции.
- [ ] Сохранять последние корректные метаданные при временной ошибке.

### Metadata polling

- базовый интервал: 15 секунд;
- `AbortController` для устаревших запросов;
- exponential backoff при ошибках;
- немедленное обновление после возвращения видимости;
- ручной refresh в Diagnostics;
- WebSocket/SSE не входит в этот этап.

### Definition of Done

- нет дублированных intervals после Elementor preview updates;
- network failure не создаёт бесконечный reconnect;
- metadata failure не останавливает direct stream;
- несколько плееров не воспроизводятся одновременно.

---

## 8. Этап 5 — Media Session и visualizer

**Цель:** улучшить системную интеграцию и визуальную обратную связь.

### Media Session

- [ ] Передавать title, artist, album и artwork.
- [ ] Обрабатывать play/pause action handlers.
- [ ] Обновлять данные только при реальной смене трека.
- [ ] Корректно очищать handlers при destroy.
- [ ] Проверить Android Chrome и поддерживаемые desktop browsers.

### Visualizer

- [ ] Ввести режимы Auto, Real, Animated и Off.
- [ ] Не подключать Web Audio analyser без подходящего CORS.
- [ ] Предоставить fallback-анимацию без анализа аудиоданных.
- [ ] Сделать разные представления Square и Vinyl.
- [ ] Отключать/упрощать анимацию при reduced motion.
- [ ] Останавливать `requestAnimationFrame` в скрытой вкладке и при destroy.

### Definition of Done

- real analyser никогда не ломает cross-origin поток;
- fallback явно является визуализацией состояния, а не заявляется точным спектром;
- Media Session не вызывает ошибок в неподдерживаемых браузерах.

---

## 9. Этап 6 — завершить темы и layouts

**Цель:** сформировать готовую адаптивную дизайн-систему.

### Дизайн-токены

- [ ] Цвет поверхности.
- [ ] Основной и accent цвет.
- [ ] Primary/muted/error text.
- [ ] Border, radius и shadow.
- [ ] Artwork size.
- [ ] Control size.
- [ ] Internal gap и padding.
- [ ] Sticky/floating offsets.

Все токены задаются под `.nfp`.

### Square

- [ ] Full desktop.
- [ ] Full mobile.
- [ ] Compact horizontal/vertical.
- [ ] Mini.
- [ ] Sticky.
- [ ] Floating.
- [ ] Light/dark.
- [ ] Long metadata and missing artwork.

### Vinyl

- [x] Full desktop baseline.
- [x] Full mobile baseline без горизонтального overflow.
- [x] Пластинка, нейроморфная тень и тонарм сохраняют позицию при переходе в одноколоночный layout.
- [x] Тонарм начинается на внешней дорожке и использует duration/elapsed только при валидных metadata.
- [x] Station label выводится на desktop и mobile; selector station скрыт до реализации нескольких станций.
- [ ] Compact.
- [ ] Mini.
- [ ] Sticky/floating адаптация либо явное ограничение поддерживаемых layouts.
- [ ] Light/dark.
- [ ] Reduced motion.

### Responsive matrix

Проверять минимум:

- 320 px;
- 375 px;
- 768 px;
- 1024 px;
- 1440 px;
- контейнер Elementor уже viewport;
- RTL;
- safe-area mobile devices.

### Definition of Done

- обе темы закончены и не зависят от preview-only CSS;
- нет горизонтального overflow;
- touch targets не менее 44×44;
- host-theme styles не меняются;
- все visual rules остаются под `.nfp`.

---

## 10. Этап 7 — админ-конструктор

**Цель:** создать Material-inspired интерфейс настройки, основанный на предоставленном референсе AzuraCast embed builder.

### Информационная архитектура

- [ ] Обзор.
- [ ] Подключение.
- [ ] Конструктор.
- [ ] Интеграции.
- [ ] Диагностика.
- [ ] Настройки.

### Конструктор

- [ ] Двухколоночный desktop layout.
- [ ] Последовательный mobile layout.
- [ ] Выбор Square/Vinyl.
- [ ] Выбор light/dark.
- [ ] Вкладки «Оформление», «Функциональность», «Макет», «Расширенное».
- [ ] Live preview.
- [ ] Сброс текущего пресета.
- [ ] Сохранение нескольких presets одной станции.
- [ ] Shortcode output.
- [ ] PHP snippet output.
- [ ] Clipboard с доступным status message.
- [ ] Валидация ширины, высоты и цветов.

### Ограничения первой версии

- тип виджета только «Радиоплеер»;
- iframe embed отсутствует;
- custom CSS лучше отложить до отдельного security/UX review;
- импорт/экспорт реализуется только после стабилизации схемы пресетов.

### Material-inspired, но WordPress-native

- без удалённого Material UI и без production framework;
- WordPress admin typography и capabilities;
- карточки, tabs, status chips и switches;
- полноценные focus states;
- семантические формы и связанные labels/errors;
- отсутствие копирования интерфейса AzuraCast один в один.

### Definition of Done

- все поля сохраняются через WordPress Settings API/защищённые endpoints;
- live preview использует production renderer;
- keyboard и screen reader flow проверены;
- конструктор работает на narrow viewport.

---

## 11. Этап 8 — Gutenberg block

**Цель:** добавить нативное размещение в block editor.

### Задачи

- [ ] Зарегистрировать dynamic block `net-fm-player/player`.
- [ ] Добавить `block.json`.
- [ ] Использовать server-side render.
- [ ] Выбирать сохранённый preset.
- [ ] Разрешить ограниченные local overrides.
- [ ] Добавить inspector controls.
- [ ] Показывать понятное состояние при ненастроенной станции.
- [ ] Не дублировать frontend renderer.
- [ ] Проверить editor iframe и frontend.

### Definition of Done

- блок сохраняет только стабильные attributes;
- изменение preset отражается в editor preview;
- frontend совпадает с shortcode;
- обновление плагина не ломает сохранённый block markup.

---

## 12. Этап 9 — Elementor integration 1.0

**Цель:** синхронизировать Elementor с новой моделью presets и конфигурации.

### Задачи

- [ ] Сохранить widget name `net-fm-player`.
- [ ] Добавить выбор preset.
- [ ] Разделить Content, Style и Advanced controls.
- [ ] Скрывать controls, неприменимые к выбранной теме/layout.
- [ ] Поддержать global/preset и local override режимы.
- [ ] Использовать общий renderer.
- [ ] Реинициализировать widget без duplicate listeners.
- [ ] Проверить frontend editor, preview и опубликованную страницу.
- [ ] Не требовать Elementor Pro.

### Политика совместимости

- текущая стабильная бесплатная версия Elementor на момент релиза;
- по возможности предыдущая поддерживаемая minor line;
- отсутствие фатальных ошибок, если Elementor не установлен или выключен.

### Definition of Done

- Elementor optional dependency корректно обнаруживается;
- global/local режимы работают;
- controls и shortcode не расходятся;
- editor cleanup проверен.

---

## 13. Этап 10 — Diagnostics и безопасный metadata proxy

**Цель:** сделать проблемы подключения объяснимыми и обеспечить fallback для CORS.

### Diagnostics

- [ ] API availability.
- [ ] Station status.
- [ ] Mount list.
- [ ] Metadata CORS.
- [ ] Mixed content.
- [ ] Artwork availability.
- [ ] Last successful update.
- [ ] Active fallback.
- [ ] WordPress/PHP/Elementor versions.
- [ ] Копируемый безопасный report.

### Proxy

- [ ] Выключен по умолчанию.
- [ ] Только metadata JSON.
- [ ] Только сохранённый AzuraCast origin.
- [ ] HTTPS.
- [ ] Запрет private/link-local/loopback.
- [ ] Ограничение redirects, timeout и body size.
- [ ] Transient cache.
- [ ] WordPress HTTP API.
- [ ] REST permission/rate control.
- [ ] Privacy disclosure.
- [ ] Никакого аудиопроксирования.

### Definition of Done

- proxy не является open proxy;
- SSRF test cases закрыты;
- direct browser mode остаётся default;
- диагностика не раскрывает секреты.

---

## 14. Этап 11 — доступность, локализация и безопасность

**Цель:** подготовить feature-complete candidate к публичному релизу.

### Accessibility

- [ ] Native controls.
- [ ] Play/pause and mute accessible names.
- [ ] Focus-visible.
- [ ] Keyboard-only flow.
- [ ] Range labels and values.
- [ ] Status/error live regions.
- [ ] Contrast.
- [ ] 44×44 targets.
- [ ] Reduced motion.
- [ ] Screen reader smoke test.

### Localization

- [ ] English source strings.
- [ ] Russian translation.
- [ ] JavaScript localization.
- [ ] Number/plural handling.
- [ ] RTL smoke test.

### Security

- [ ] Capability checks.
- [ ] Nonces.
- [ ] Sanitization.
- [ ] Context escaping.
- [ ] REST permission callbacks.
- [ ] SSRF protections.
- [ ] No secret leakage.
- [ ] Safe preset import when implemented.

### Definition of Done

- нет blocker/critical findings;
- обе локали проходят основные flows;
- keyboard и screen reader smoke tests завершены.

---

## 15. Этап 12 — uninstall, packaging и WordPress.org

**Цель:** выпустить версию 1.0.

### Data lifecycle

- [ ] Deactivate сохраняет данные.
- [ ] Delete по умолчанию удаляет plugin data.
- [ ] Настройка позволяет заранее сохранить данные после Delete.
- [ ] `uninstall.php` защищён от прямого запуска.
- [ ] Удаляются options, presets, transients и cache.

### WordPress.org package

- [ ] `readme.txt`.
- [ ] GPL declaration.
- [ ] External service disclosure.
- [ ] Privacy policy suggestion.
- [ ] Changelog.
- [ ] Screenshots.
- [ ] Banner/icon только с правами на использование.
- [ ] Отсутствуют `.git`, `.kilo`, `.vscode`, preview logs, node_modules и generated archives.
- [ ] Plugin Check.
- [ ] Clean install test.
- [ ] Upgrade test с baseline.

### Release matrix

- [ ] WordPress minimum.
- [ ] WordPress current stable.
- [ ] PHP 8.2.
- [ ] PHP 8.3.
- [ ] PHP 8.4.
- [ ] PHP 8.5.
- [ ] Elementor absent/current.
- [ ] Direct stream.
- [ ] AzuraCast success/CORS failure/offline.
- [ ] Chrome/Firefox/Safari/Edge.
- [ ] iOS Safari/Android Chrome.

### Definition of Done

- создан воспроизводимый release ZIP;
- Plugin Check не содержит release blockers;
- migration/uninstall проверены;
- выпущена версия 1.0.0.

---

# Ближайшая разработка

## 16. Ближайший этап — несколько станций и выбор

**Цель:** заменить неактивный визуальный placeholder меню на доступный выбор одной из нескольких настроенных станций.

- [ ] Ввести список station profiles без нарушения single-station конфигурации baseline.
- [ ] Добавить migration из текущих `net_fm_player_options`.
- [ ] Реализовать доступное меню выбора станции с клавиатурным управлением.
- [ ] Переключать stream, metadata polling, station label и artwork без duplicate timers/listeners.
- [ ] Сохранять выбранную станцию локально в namespaced storage.
- [ ] Добавить selector station profile в shortcode и Elementor.
- [ ] Проверить primary/secondary/HLS fallback для каждой станции.

# После версии 1.0

## 17. Версия 1.1 — расширенные listener data

- [ ] `playing_next`.
- [ ] История из `song_history`.
- [ ] Расписание.
- [ ] Live DJ improvements.
- [ ] WebSocket/SSE как progressive enhancement.
- [ ] Polling fallback остаётся обязательным.

## 18. Версия 1.2 — собственный рейтинг

AzuraCast не предоставляет стандартный рейтинг треков, поэтому система реализуется в WordPress.

- [ ] REST endpoint like/dislike.
- [ ] Серверная проверка текущего трека.
- [ ] Идентификатор station/song/played_at.
- [ ] Защита от повторных голосов.
- [ ] Rate limiting.
- [ ] Privacy and retention settings.
- [ ] Агрегированная статистика.
- [ ] CSV/JSON export.
- [ ] Webhook для внешней автоматизации.
- [ ] Явное пояснение, что рейтинг не меняет AzuraCast AutoDJ.

## 19. Версия 1.3 — requests и подкасты

- [ ] Listener requests.
- [ ] Обработка 403/429/500.
- [ ] Client cooldown.
- [ ] Podcast lists and episode player.
- [ ] Отдельные widget types в конструкторе.

## 20. Версия 1.4 — расширенное управление несколькими станциями

- [ ] Station repository.
- [ ] Несколько connection profiles.
- [ ] Binding preset to station.
- [ ] Station switcher.
- [ ] Shared polling by station.
- [ ] Migration from single-station options.

Этот этап может стать основанием будущей коммерческой редакции, но решение принимается отдельно. Бесплатный код не должен заранее содержать неиспользуемую лицензионную инфраструктуру.

## 21. Версия 1.5 — HLS и приватные станции

- [x] Native HLS detection.
- [ ] Локально поставляемый HLS fallback только после оценки лицензии и размера.
- [ ] CORS diagnostics.
- [ ] Server-side integration для приватных station data.
- [ ] Keys только server-side и с минимальными правами.
- [ ] Никогда не отдавать API key браузеру.

## 22. Версия 1.6 — внешний embed

- [ ] WordPress-hosted iframe endpoint.
- [ ] Responsive embed code.
- [ ] CSP/frame policy guidance.
- [ ] Preset binding.
- [ ] `postMessage` только с проверкой origin.
- [ ] External service/privacy documentation.

## 23. Экспериментальная ветка — Continuous Playback

Настоящее бесшовное воспроизведение требует постоянного player shell и изменения навигации сайта.

- [ ] Proof of concept с History API.
- [ ] Allowlist внутренних ссылок.
- [ ] Exclusions для forms, downloads, admin, checkout и сторонних scripts.
- [ ] Совместимость с выбранными темами.
- [ ] Accessible focus management после AJAX navigation.
- [ ] Browser back/forward.
- [ ] SEO/canonical validation.
- [ ] Автоматический fallback на обычную навигацию.

Функция не включается по умолчанию и не блокирует основной roadmap.

# 24. Сквозная проверка каждого этапа

После изменений PHP:

```powershell
php -l net-fm-player.php
php -l includes/class-net-fm-elementor-widget.php
git diff --check
```

При появлении новых PHP-файлов они также проходят `php -l`.

Для browser-facing изменений вручную проверяются:

1. shortcode с global settings;
2. shortcode с local overrides;
3. Elementor global/local;
4. Gutenberg editor/frontend после появления блока;
5. direct stream без API;
6. AzuraCast metadata success;
7. metadata unavailable/CORS;
8. primary/secondary/mount switch;
9. play/pause/mute/volume/reconnect;
10. desktop/mobile/narrow container;
11. keyboard/reduced motion;
12. несколько экземпляров и cleanup.

# 25. Очерёдность ближайших работ

Ближайший безопасный порядок разработки:

1. Несколько станций и доступное меню выбора.
2. Этап 1 — identity, headers и localization foundation.
3. Этап 2 — модульное ядро без изменения UI.
4. Этап 3 — подключение и mount discovery.
5. Этап 4 — frontend lifecycle.
6. Этап 5 — Media Session и visualizer.
7. Этап 6 — Square/Vinyl и responsive layouts.
8. Этап 7 — админ-конструктор.
9. Этапы 8–9 — Gutenberg и Elementor.
10. Этапы 10–12 — diagnostics, hardening и релиз.

Нельзя начинать внешний embed, рейтинг или continuous navigation до стабилизации базовой многосоставной конфигурации станций и версии 1.0.
