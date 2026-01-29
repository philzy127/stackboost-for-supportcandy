# Plugin Check Report

**Plugin:** StackBoost - For SupportCandy
**Generated at:** 2026-01-22 12:44:06


## `src/Modules/OnboardingDashboard/Admin/ImportExport.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 271 | 14 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$json_content'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 289 | 11 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_FILES[&#039;import_file&#039;] |  |

## `src/WordPress/Admin/Settings.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 1105 | 30 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$wp_filesystem'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1171 | 73 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;stackboost_settings&#039;] |  |

## `src/Modules/OnboardingDashboard/Data/TicketService.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 38 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
| 94 | 18 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 94 | 18 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 106 | 38 | ERROR | WordPress.DB.PreparedSQL.NotPrepared | Use placeholders and $wpdb->prepare(); found $query |  |
| 110 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 110 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 110 | 35 | ERROR | WordPress.DB.PreparedSQL.NotPrepared | Use placeholders and $wpdb->prepare(); found $prepared_query |  |

## `src/Modules/Directory/Admin/Management.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 236 | 4 | WARNING | Squiz.PHP.DiscouragedFunctions.Discouraged | The use of function set_time_limit() is discouraged |  |
| 460 | 50 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 460 | 50 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 522 | 13 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_fopen | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: fopen(). |  |
| 584 | 3 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_fclose | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: fclose(). |  |

## `src/Admin/Upgrade.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 117 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 117 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 117 | 50 | ERROR | WordPress.DB.PreparedSQL.NotPrepared | Use placeholders and $wpdb->prepare(); found $sql |  |

## `src/Modules/OnboardingDashboard/Admin/Settings.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 259 | 17 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 259 | 48 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 285 | 15 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 534 | 15 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |

## `src/Modules/AfterTicketSurvey/Install.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 76 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 76 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 78 | 31 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 78 | 31 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 78 | 38 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results($wpdb-&gt;prepare( &quot;SHOW COLUMNS FROM \`{$safe_table}\` LIKE %s&quot;, &#039;prefill_key&#039; ))\n$safe_table assigned unsafely at line 77:\n $safe_table = $this-&gt;questions_table_name |  |
| 78 | 67 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SHOW COLUMNS FROM \`{$safe_table}\` LIKE %s&quot; |  |
| 79 | 32 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 79 | 32 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 79 | 39 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results($wpdb-&gt;prepare( &quot;SHOW COLUMNS FROM \`{$safe_table}\` LIKE %s&quot;, &#039;is_readonly_prefill&#039; ))\n$safe_table assigned unsafely at line 77:\n $safe_table = $this-&gt;questions_table_name |  |
| 79 | 68 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SHOW COLUMNS FROM \`{$safe_table}\` LIKE %s&quot; |  |
| 170 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 170 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 176 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 176 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 176 | 15 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_var(&quot;SELECT COUNT(*) FROM \`{$safe_table}\`&quot;)\n$safe_table assigned unsafely at line 175:\n $safe_table = $this-&gt;questions_table_name |  |
| 176 | 24 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT COUNT(*) FROM \`{$safe_table}\`&quot; |  |
| 191 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 206 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |

## `src/Modules/AfterTicketSurvey/Repository.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 46 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 46 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 46 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results(&quot;SELECT * FROM \`{$safe_table}\` ORDER BY sort_order ASC&quot;)\n$safe_table assigned unsafely at line 45:\n $safe_table = $this-&gt;questions_table_name |  |
| 46 | 30 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT * FROM \`{$safe_table}\` ORDER BY sort_order ASC&quot; |  |
| 58 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 58 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 58 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_row($wpdb-&gt;prepare( &quot;SELECT * FROM \`{$safe_table}\` WHERE id = %d&quot;, $id ))\n$safe_table assigned unsafely at line 57:\n $safe_table = $this-&gt;questions_table_name |  |
| 58 | 42 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT * FROM \`{$safe_table}\` WHERE id = %d&quot; |  |
| 69 | 22 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 69 | 22 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 69 | 23 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_var(&quot;SELECT MAX(sort_order) FROM \`{$safe_table}\`&quot;)\n$safe_table assigned unsafely at line 68:\n $safe_table = $this-&gt;questions_table_name |  |
| 69 | 32 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT MAX(sort_order) FROM \`{$safe_table}\`&quot; |  |
| 80 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 80 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 80 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_var($wpdb-&gt;prepare( &quot;SELECT id FROM \`{$safe_table}\` WHERE question_type = %s&quot;, &#039;ticket_number&#039; ))\n$safe_table assigned unsafely at line 79:\n $safe_table = $this-&gt;questions_table_name |  |
| 80 | 42 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT id FROM \`{$safe_table}\` WHERE question_type = %s&quot; |  |
| 91 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 104 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 104 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 115 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 115 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 127 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 127 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 127 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results($wpdb-&gt;prepare( &quot;SELECT option_value FROM \`{$safe_table}\` WHERE question_id = %d ORDER BY sort_order ASC&quot;, $question_id ))\n$safe_table assigned unsafely at line 126:\n $safe_table = $this-&gt;dropdown_options_table_name |  |
| 127 | 46 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT option_value FROM \`{$safe_table}\` WHERE question_id = %d ORDER BY sort_order ASC&quot; |  |
| 138 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 138 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 149 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 160 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 160 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 160 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results(&quot;SELECT id, submission_date FROM \`{$safe_table}\` ORDER BY submission_date DESC&quot;)\n$safe_table assigned unsafely at line 159:\n $safe_table = $this-&gt;survey_submissions_table_name |  |
| 160 | 30 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT id, submission_date FROM \`{$safe_table}\` ORDER BY submission_date DESC&quot; |  |
| 171 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 171 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 171 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results(&quot;SELECT s.*, u.display_name FROM \`{$safe_table}\` s LEFT JOIN {$wpdb-&gt;users} u ON s.user_id = u.ID ORDER BY submission_date DESC&quot;)\n$safe_table assigned unsafely at line 170:\n $safe_table = $this-&gt;survey_submissions_table_name |  |
| 171 | 30 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT s.*, u.display_name FROM \`{$safe_table}\` s LEFT JOIN {$wpdb-&gt;users} u ON s.user_id = u.ID ORDER BY submission_date DESC&quot; |  |
| 182 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 194 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 194 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 214 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 214 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 214 | 10 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_submissions used in $wpdb-&gt;query($wpdb-&gt;prepare( &quot;DELETE FROM \`{$safe_submissions}\` WHERE id IN ($placeholders)&quot;, $ids ))\n$safe_submissions assigned unsafely at line 211:\n $safe_submissions = $this-&gt;survey_submissions_table_name |  |
| 214 | 33 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_submissions} at &quot;DELETE FROM \`{$safe_submissions}\` WHERE id IN ($placeholders)&quot; |  |
| 214 | 33 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable $placeholders at &quot;DELETE FROM \`{$safe_submissions}\` WHERE id IN ($placeholders)&quot; |  |
| 214 | 96 | WARNING | WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare | Replacement variables found, but no valid placeholders found in the query. |  |
| 216 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 216 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 216 | 10 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_answers used in $wpdb-&gt;query($wpdb-&gt;prepare( &quot;DELETE FROM \`{$safe_answers}\` WHERE submission_id IN ($placeholders)&quot;, $ids ))\n$safe_answers assigned unsafely at line 212:\n $safe_answers = $this-&gt;survey_answers_table_name |  |
| 216 | 33 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_answers} at &quot;DELETE FROM \`{$safe_answers}\` WHERE submission_id IN ($placeholders)&quot; |  |
| 216 | 33 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable $placeholders at &quot;DELETE FROM \`{$safe_answers}\` WHERE submission_id IN ($placeholders)&quot; |  |
| 216 | 103 | WARNING | WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare | Replacement variables found, but no valid placeholders found in the query. |  |
| 227 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 239 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 239 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 239 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_answers used in $wpdb-&gt;get_results($wpdb-&gt;prepare( &quot;SELECT answer_text, rating FROM \`{$safe_answers}\` WHERE question_id = %d&quot;, $question_id ))\n$safe_answers assigned unsafely at line 238:\n $safe_answers = $this-&gt;survey_answers_table_name |  |
| 239 | 46 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_answers} at &quot;SELECT answer_text, rating FROM \`{$safe_answers}\` WHERE question_id = %d&quot; |  |
| 251 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 251 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 251 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_answers used in $wpdb-&gt;get_results($wpdb-&gt;prepare( &quot;SELECT question_id, answer_value FROM \`{$safe_answers}\` WHERE submission_id = %d&quot;, $submission_id ))\n$safe_answers assigned unsafely at line 250:\n $safe_answers = $this-&gt;survey_answers_table_name |  |
| 251 | 46 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_answers} at &quot;SELECT question_id, answer_value FROM \`{$safe_answers}\` WHERE submission_id = %d&quot; |  |

## `src/Modules/Directory/Data/MetaBoxes.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 276 | 27 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;sb_staff_dir_meta_box_nonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 276 | 27 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;sb_staff_dir_meta_box_nonce&#039;] |  |
| 293 | 30 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 293 | 30 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 312 | 54 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[$field] |  |
| 399 | 27 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;sb_location_details_meta_box_nonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 399 | 27 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;sb_location_details_meta_box_nonce&#039;] |  |

## `src/Core/Request.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 29 | 17 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 33 | 24 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 33 | 24 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_GET[$key] |  |
| 47 | 17 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 51 | 24 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 51 | 24 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[$key] |  |
| 66 | 17 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 70 | 24 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 70 | 24 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_REQUEST[$key] |  |
| 82 | 17 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 86 | 11 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 86 | 11 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_FILES[$key] |  |
| 107 | 17 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 117 | 17 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 127 | 17 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |

## `src/Integration/SupportCandyRepository.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 30 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 30 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 30 | 21 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results(&quot;SELECT slug, name FROM \`{$safe_table}\`&quot;)\n$safe_table assigned unsafely at line 28:\n $safe_table = $custom_fields_table\n$custom_fields_table assigned unsafely at line 27:\n $custom_fields_table = $wpdb-&gt;prefix . &#039;psmsc_custom_fields&#039; |  |
| 30 | 34 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT slug, name FROM \`{$safe_table}\`&quot; |  |
| 46 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 46 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 46 | 21 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results($wpdb-&gt;prepare( &quot;SELECT slug, name FROM \`{$safe_table}\` WHERE type = %s&quot;, $type ))\n$safe_table assigned unsafely at line 44:\n $safe_table = $custom_fields_table\n$custom_fields_table assigned unsafely at line 43:\n $custom_fields_table = $wpdb-&gt;prefix . &#039;psmsc_custom_fields&#039; |  |
| 47 | 20 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT slug, name FROM \`{$safe_table}\` WHERE type = %s&quot; |  |
| 64 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 64 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 64 | 21 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_results(&quot;SELECT id, name FROM \`{$safe_table}\` ORDER BY name ASC&quot;)\n$safe_table assigned unsafely at line 62:\n $safe_table = $status_table\n$status_table assigned unsafely at line 61:\n $status_table = $wpdb-&gt;prefix . &#039;psmsc_statuses&#039; |  |
| 64 | 34 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT id, name FROM \`{$safe_table}\` ORDER BY name ASC&quot; |  |
| 85 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 85 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 90 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 90 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 90 | 22 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $safe_table used in $wpdb-&gt;get_var($wpdb-&gt;prepare(\n\t\t\t\t&quot;SELECT id FROM \`{$safe_table}\` WHERE name = %s&quot;,\n\t\t\t\t$field_name\n\t\t\t))\n$safe_table assigned unsafely at line 89:\n $safe_table = $table_name\n$table_name assigned unsafely at line 81:\n $table_name = $wpdb-&gt;prefix . &#039;psmsc_custom_fields&#039;\n$table_name_like assigned unsafely at line 82:\n $table_name_like = $wpdb-&gt;esc_like( $table_name ) |  |
| 92 | 5 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$safe_table} at &quot;SELECT id FROM \`{$safe_table}\` WHERE name = %s&quot; |  |
| 112 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 112 | 14 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 116 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 116 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 143 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 143 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 143 | 17 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_results(&quot;SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = &#039;{$table_name}&#039; AND column_name = &#039;{$column_name}&#039;&quot;) |  |
| 143 | 30 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table_name} at &quot;SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = &#039;{$table_name}&#039; AND column_name = &#039;{$column_name}&#039;&quot; |  |
| 143 | 30 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$column_name} at &quot;SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = &#039;{$table_name}&#039; AND column_name = &#039;{$column_name}&#039;&quot; |  |
| 147 | 11 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;query(&quot;ALTER TABLE \`{$table_name}\` ADD COLUMN \`{$column_name}\` {$column_def} {$after_clause}&quot;)\n$column_def used without escaping.\n$after_clause assigned unsafely at line 146:\n $after_clause = $after_column ? &quot;AFTER \`{$after_column}\`&quot; : &quot;&quot;\n$after_column used without escaping. |  |
| 147 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 147 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 147 | 18 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table_name} at &quot;ALTER TABLE \`{$table_name}\` ADD COLUMN \`{$column_name}\` {$column_def} {$after_clause}&quot; |  |
| 147 | 18 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$column_name} at &quot;ALTER TABLE \`{$table_name}\` ADD COLUMN \`{$column_name}\` {$column_def} {$after_clause}&quot; |  |
| 147 | 18 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$column_def} at &quot;ALTER TABLE \`{$table_name}\` ADD COLUMN \`{$column_name}\` {$column_def} {$after_clause}&quot; |  |
| 147 | 18 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$after_clause} at &quot;ALTER TABLE \`{$table_name}\` ADD COLUMN \`{$column_name}\` {$column_def} {$after_clause}&quot; |  |
| 147 | 27 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |

## `src/Modules/AfterHoursNotice/WordPress.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 376 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
| 394 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |

## `src/Modules/Directory/WordPress.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 1176 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
| 1252 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |

## `src/Modules/Directory/Admin/LocationsListTable.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 282 | 23 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_key | Detected usage of meta_key, possible slow query. |  |
| 292 | 19 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |

## `src/Modules/Directory/Admin/StaffListTable.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 331 | 23 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_key | Detected usage of meta_key, possible slow query. |  |
| 334 | 23 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_key | Detected usage of meta_key, possible slow query. |  |
| 344 | 19 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |

## `src/Services/DirectoryService.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 73 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
| 194 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
