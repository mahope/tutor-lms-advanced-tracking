# Tutor LMS Pro – Datamodel & Dataopbygning

> **Version analyseret:** 3.6.2
> **Dato:** 15. juli 2025

Denne dokumentation beskriver den interne datamodel i **Tutor LMS Pro**‑pluginnet (pro‑udgaven af Tutor LMS). Formålet er at give et overblik over, **hvordan og hvor** pluginnet gemmer sine data, så du trygt kan bygge et eget add‑on, der læser eller skriver de samme informationer.

---

## 1. Overordnet arkitektur

Tutor LMS Pro udvider den gratis **Tutor LMS Core**‑plugin og arver derfor hovedparten af de brugerfladerede datatyper (Custom Post Types for *course*, *lesson*, *quiz*, *assignment* osv.).
Pro‑udgaven **tilføjer** først og fremmest:

* Egen **REST API** (namespace `tutor-pro/v1`)
* Flere **add‑ons** (H5P‑integration, abonnementer, notifikationer, gæste‑checkout m.m.)
* Egne **database­tabeller** til performance‑tunge features
* En lang række **options**, **post‑** og **user‑meta‑felter** som vist nedenfor.

> **OBS:** Navne på tabeller/kolonner herunder antager `wp_` som tabel‑præfiks. Tilpas hvis dit miljø bruger et andet præfiks.

---

## 2. Egendefinerede database­tabeller

| Add‑on / Feature                       | Tabel                               | Primære felter                                                                                                                                                                                                                                                         | Formål                                                                                |
| -------------------------------------- | ----------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| **Notifikationer (On‑site & Push)**    | `wp_tutor_notifications`            | `ID`, `type`, `title`, `content`, `status (READ/UNREAD)`, `receiver_id`, `post_id`, `topic_url`, `created_at`                                                                                                                                                          | Lagrer hver notifikation der sendes til en bruger.                                    |
|                                        | `wp_tutor_notification_preferences` | `id`, `user_id`, `notification_type (email, push, onsite, sms)`, `group_name`, `trigger_name`, `opt_in`                                                                                                                                                                | Den enkelte brugers opt‑in/opt‑out pr. notifikations‑trigger.                         |
| **H5P‑statistik**                      | `wp_tutor_h5p_statement`            | `statement_id`, `instructor_id`, `content_id`, `user_id`, `verb`, `verb_id`, `activity_name`, `activity_description`, `score_raw`, `score_max`, `passed`, `completed`, `duration`, `created_at`                                                                        | Generel xAPI‐statement log for H5P‑indhold.                                           |
|                                        | `wp_tutor_h5p_quiz_statement`       | `statement_id`, `instructor_id`, `course_id`, `topic_id`, `quiz_id`, `question_id`, `content_id`, `user_id`, `verb`, `verb_id`, `raw_score`, `max_score`, `scaled_score`, `success`, `completion`, `opened`, `answered`, `duration`                                    | Statement log specifikt for H5P‐quiz‑interaktioner.                                   |
|                                        | `wp_tutor_h5p_quiz_result`          | `result_id`, `quiz_id`, `attempt_id`, `question_id`, `user_id`, `content_id`, `response`, `max_score`, `raw_score`, `scaled_score`, `min_score`, `completion`, `success`, `opened`, `answered`, `duration`, `created_at`                                               | *Denormaliseret* resultat­tabel til hurtig visning i rapporter.                       |
|                                        | `wp_tutor_h5p_lesson_statement`     | `statement_id`, `instructor_id`, `course_id`, `topic_id`, `lesson_id`, `content_id`, `user_id`, `verb`, …                                                                                                                                                              | Statement log for H5P embed­det i lessons.                                            |
| **Abonnementer (Subscription add‑on)** | `wp_tutor_subscription_plans`       | `id`, `payment_type (recurring / onetime)`, `plan_type (course / full_site)`, `restriction_mode (include / exclude)`, `plan_name`, `short_description`, `description`, `price`, `billing_interval`, `trial_days`, `max_courses`, `is_featured`, `status`, `created_at` | Master­re­gister for salgbare abonnementer.                                           |
|                                        | `wp_tutor_subscription_plan_items`  | `id`, `plan_id`, `item_id`, `item_type (course_id / category_id / bundle_id)`, `restriction_type`                                                                                                                                                                      | Kobler planer til de konkrete kurser eller kategorier de omfatter.                    |
|                                        | `wp_tutor_subscriptions`            | `id`, `plan_id`, `user_id`, `order_id`, `transaction_id`, `status (active / cancelled / expired)`, `started_at`, `expired_at`, `next_payment_at`, `cancelled_at`, `created_at`                                                                                         | Den enkelte brugers kørende abonnement(er).                                           |
|                                        | `wp_tutor_subscriptionmeta`         | `id`, `subscription_id`, `meta_key`, `meta_value`                                                                                                                                                                                                                      | Fleksibel meta­tabel til supplerende data pr. abonnement (f.eks. Stripe‑customer‑id). |

### 2.1 Egenskaber ved tabellerne

* **Charset / Collation** arves fra `$wpdb->get_charset_collate()`.
* Alle fremmednøgler defineres eksplicit med `ON DELETE CASCADE` for at undgå forældreløse rækker.
* Alle primære nøgler er auto‑increment (`BIGINT(20) UNSIGNED`).
* Tidsstempler gemmes som `datetime` (ikke `timestamp`) af hensyn til host‑afvigelser.

---

## 3. Custom Post Types & Taxonomies (fra Core)

Selv om Pro‑udgaven **ikke** registrerer yderligere CPT’er, er det vigtigt at kende kerne‑typerne, da Pro ofte lægger ekstra meta på dem:

| CPT                                 | Beskrivelse (kort)           | Typiske Pro‐meta                                                                                                                                                                                                                                                                                                                      |
| ----------------------------------- | ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `courses` (`tutor_course`)          | Overordnede kursus­poster    | `_tutor_course_price_type`, `_tutor_course_price`, `_tutor_course_level`, `_tutor_course_benefits`, `_tutor_course_requirements`, `_tutor_course_target_audience`, `_tutor_course_material_includes`, `_tutor_course_prerequisites_ids`, `_tutor_disable_certificate`, `tutor_course_certificate_template`, `_tutor_is_public_course` |
| `lessons` (`tutor_lesson`)          | Enkelte lektioner i et topic | `_tutor_video_source`, `_tutor_video_poster`, `_tutor_video_duration`, `_tutor_attachments`, `_content_drip_settings`                                                                                                                                                                                                                 |
| `topics` (`tutor_topic`)            | Samler lessons i kapitler    | – (Pro lægger primært display‑indstillinger i options)                                                                                                                                                                                                                                                                                |
| `quizzes` (`tutor_quiz`)            | Quiz‑container               | `_tutor_quiz_attempt_limit`, `_tutor_quiz_passing_grade`, `_tutor_quiz_layout`, `_tutor_quiz_feedback_mode`, `_tutor_quiz_randomize`                                                                                                                                                                                                  |
| `assignments` (`tutor_assignments`) | Opgave‑afleveringer          | `_tutor_assignment_total_mark`, `_tutor_assignment_pass_mark`, `_tutor_assignment_attachments`, `_tutor_assignment_evaluate_mark`, `instructor_feedback`                                                                                                                                                                              |

> **Tip:** Brug WP‑funktionen `get_post_meta( $post_id )` for at se samtlige felter på et enkelt kursus i testmiljøet.

---

## 4. Meta‑felter

### 4.1 Post meta (udsnit)

```text
_tutor_course_level
_tutor_course_price_type
_tutor_course_price
_tutor_course_prerequisites_ids
_tutor_course_material_includes
_tutor_assignment_total_mark
_tutor_assignment_evaluate_mark
_tutor_disable_certificate
bundle-course-ids
...
```

> **Se kildekoden i `addons/*`‑mapperne for komplette lister.**

### 4.2 User meta (Pro)

| Meta‑key                            | Bruges til                                                      | Sættes af                    |
| ----------------------------------- | --------------------------------------------------------------- | ---------------------------- |
| `_is_tutor_instructor`              | Flag der gør en WP‑bruger til **instructor**                    | Instructor‐registration flow |
| `_tutor_instructor_status`          | `approved`, `pending`, `blocked`                                | Admin‑review                 |
| `tutor_instructor_amount` + `_type` | Provisions­model (fast/%)                                       | Revenue share add‑on         |
| `_tutor_profile_*`                  | Offentlige profilfelter (twitter, linkedin, job\_title, bio, …) | Profil­editor                |
| `_tutor_h5p_lesson_completed_*`     | Per‐lesson progress IDs                                         | H5P add‑on                   |

---

## 5. Options (`wp_options`)

Tutor LMS Pro grupperer sine indstillinger efter feature, typisk som enkle bools eller serialiserede arrays.
Her er et ikke‑udtømmende udvalg, organiseret alfabetisk:

| Option key                        | Default / Type                   | Hvad gør den?                                            |
| --------------------------------- | -------------------------------- | -------------------------------------------------------- |
| `enable_facebook_login`           | `0` (bool)                       | Slår social login til                                    |
| `enable_course_share`             | `1`                              | Viser social share‑knapper på kursuslanding              |
| `membership_only_mode`            | `0`                              | Tvinger køb via Subscription add‑on                      |
| `monetize_by`                     | `course_price` / `woo` / `pmpro` | Global monetization‐driver                               |
| `device_limit`                    | integer                          | Hvor mange samtidige logins en studerende må have        |
| `tutor_license_info`              | array                            | Gemmer Pro‐licens + hash                                 |
| `supported_video_sources`         | array                            | Liste med tilladte videotyper (html5, youtube, vimeo, …) |
| `control_video_lesson_completion` | bool                             | Markerer videolektion som gennemført efter X % set       |

> Fælles for alle option‑nøgler: **Autoload** er sat til `yes` for settings der bruges i front‑load‑flowet (fx `monetize_by`), men `no` for tunge arrays (fx `tutor_license_info`).

---

## 6. REST API‑endpoints

Pro tilføjer egne kontroller i `rest-api/Controllers/*`. De udstilles under **`/wp-json/tutor-pro/v1/`** og dækker fx:

* `courses/<id>/certificate` – Genererer/bruger certifikat
* `subscriptions/plans` – CRUD på abonnement‑planer
* `notifications` – Henter (og markerer) notifikationer
* `h5p/statements` – Læser xAPI‑statements

Alle endpoints kræver **nonce**‑validering eller **JWT** afhængig af indstilling.

---

## 7. Hooks der kan udnyttes i dit add‑on

* `do_action( 'tutor_subscription_activated', $subscription_id )`
* `do_action( 'tutor_h5p_statement_saved', $statement_id, $data )`
* `apply_filters( 'tutor_course_price', $price, $course_id )`
* `apply_filters( 'tutor_is_notification_enabled_for_user', $enabled, $user_id, $group, $trigger, $channel )`

---

## 8. Migrations & Versionering

* Pluginnet tjekker `get_option( 'tutor_pro_version' )` mod `TUTOR_PRO_VERSION` ved `plugins_loaded` og kører **migrations** fra `includes/functions.php` og enkelte `*/Database.php`‑filer.
* Migrerings‐scripts bruger `dbDelta()` for schema‑ændringer, så dine egne tabeller bør have **version‐options** i samme stil, fx `my_addon_version`.

---

## 9. Best Practices for Add‑ons

1. **Benyt eksisterende meta‑felter** frem for at oprette nye, hvor det giver mening – det sparer ekstra queries.

2. Brug `tutor_utils()->table_exists( $table )` hvis du vil oprette egne tabeller, så du følger deres helper‑konvention.

3. Husk at loade din kode på hook’et `plugins_loaded` **efter** både Tutor LMS Core **og** Pro, fx:

   ```php
   add_action( 'plugins_loaded', function () {
       if ( defined( 'TUTOR_PRO_VERSION' ) ) {
           // din init‑kode her
       }
   } );
   ```

4. Udnyt Pro’s **REST‑routes** fremfor at opfinde dine egne, når du blot skal hente kursus‑ eller abonnementsdata fra en SPA / headless‑frontend.

---

## 10. Ordliste

| Begreb        | Forklaring                                                                    |
| ------------- | ----------------------------------------------------------------------------- |
| **Statement** | xAPI record sendt fra H5P‑player (verb + object + result).                    |
| **Plan**      | Et køb‑ eller betalingssetup som defineret i Subscription add‑on.             |
| **Trigger**   | En begivenhed (fx “student\_submitted\_quiz”) der kan udløse en notifikation. |

---

### Kontakt / Support

Der er ingen officiel DB‑dokumentation fra Themeum; denne fil er baseret på *static code analysis* af version 3.6.2 (Pro) plus erfaring fra produktion. Kom gerne med rettelser.

---

© 2025 – Udarbejdet af ChatGPT for **Mads Holst Jensen**.
