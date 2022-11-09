<?php

class Plugin_Test extends WP_UnitTestCase {
	/**
	 * @var MockAction
	 */
	protected $download_language_packs_action;

	public function set_up() {
		parent::set_up();

		// Allows removing newly added language files but keeping the ones
		// already provided by the test suite.
		self::$ignore_files = array_merge( self::$ignore_files, $this->files_in_dir( WP_LANG_DIR ) );

		$this->rmdir( WP_LANG_DIR );

		$GLOBALS['l10n']          = array();
		$GLOBALS['l10n_unloaded'] = array();

		$this->download_language_packs_action = new MockAction();

		add_filter( 'preferred_languages_download_language_packs', array( $this->download_language_packs_action, 'filter' ) );
	}

	public function tear_down() {
		update_option( 'preferred_languages', '' );
		update_site_option( 'preferred_languages', '' );

		$this->rmdir( WP_LANG_DIR );

		parent::tear_down();
	}

	public function grant_do_not_allow( $allcaps ) {
		$allcaps['do_not_allow'] = true;
		return $allcaps;
	}

	/**
	 * @covers ::preferred_languages_register_setting
	 */
	public function test_register_setting() {
		preferred_languages_register_setting();
		$this->assertArrayHasKey( 'preferred_languages', get_registered_settings() );
		$this->assertSame(
			10,
			has_filter( 'sanitize_option_preferred_languages', 'preferred_languages_sanitize_list' )
		);
	}

	/**
	 * @covers ::preferred_languages_register_meta
	 */
	public function test_register_meta() {
		preferred_languages_register_meta();
		$this->assertTrue( registered_meta_key_exists( 'user', 'preferred_languages' ) );
		$this->assertSame(
			10,
			has_filter( 'sanitize_user_meta_preferred_languages', 'preferred_languages_sanitize_list' )
		);
	}

	/**
	 * @covers ::preferred_languages_is_locale_switched
	 */
	public function test_is_locale_switched_early() {
		$backup = $GLOBALS['wp_locale_switcher'];
		unset( $GLOBALS['wp_locale_switcher'] );
		$actual                        = preferred_languages_is_locale_switched();
		$GLOBALS['wp_locale_switcher'] = $backup;
		$this->assertFalse( $actual );
	}

	/**
	 * @covers ::preferred_languages_is_locale_switched
	 */
	public function test_is_locale_switched_false() {
		$actual = preferred_languages_is_locale_switched();
		$this->assertSame( is_locale_switched(), $actual );
	}

	/**
	 * @covers ::preferred_languages_is_locale_switched
	 */
	public function test_is_locale_switched_true() {
		$is_switched = switch_to_locale( 'de_DE' );
		$actual      = preferred_languages_is_locale_switched();
		$actual_core = is_locale_switched();
		restore_current_locale();
		$this->assertTrue( $is_switched );
		$this->assertSame( $is_switched, $actual );
		$this->assertSame( $actual_core, $actual );
	}

	/**
	 * @covers ::preferred_languages_update_user_option
	 */
	public function test_update_user_option_no_nonce() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$_POST['preferred_languages'] = 'de_DE,fr_FR';
		preferred_languages_update_user_option( $user_id );
		$actual = get_user_meta( $user_id, 'preferred_languages', true );
		$this->assertSame( '', $actual );
	}

	/**
	 * @covers ::preferred_languages_update_user_option
	 */
	public function test_update_user_option_wrong_nonce() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$_POST['_wpnonce']            = 'foo';
		$_POST['preferred_languages'] = 'de_DE,fr_FR';
		preferred_languages_update_user_option( $user_id );
		$actual = get_user_meta( $user_id, 'preferred_languages', true );
		$this->assertSame( '', $actual );
	}

	/**
	 * @covers ::preferred_languages_update_user_option
	 */
	public function test_update_user_option_no_capability() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$_POST['_wpnonce']            = wp_create_nonce( 'update-user_' . $user_id );
		$_POST['preferred_languages'] = 'de_DE,fr_FR';

		preferred_languages_update_user_option( $user_id );
		$actual = get_user_meta( $user_id, 'preferred_languages', true );
		$this->assertSame( '', $actual );
	}

	/**
	 * @covers ::preferred_languages_update_user_option
	 */
	public function test_update_user_option() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		$_POST['_wpnonce']            = wp_create_nonce( 'update-user_' . $user_id );
		$_POST['preferred_languages'] = 'de_DE,es_ES';
		preferred_languages_update_user_option( $user_id );
		$actual = get_user_meta( $user_id, 'preferred_languages', true );
		$this->assertSame( 'de_DE,es_ES', $actual );
	}

	/**
	 * @covers ::preferred_languages_get_user_list
	 */
	public function test_get_user_list() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,es_ES' );

		$expected = array(
			'de_DE',
			'es_ES',
		);

		$this->assertSame( $expected, preferred_languages_get_user_list() );
	}

	/**
	 * @covers ::preferred_languages_get_user_list
	 */
	public function test_get_user_list_user_id() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		update_user_meta( $user_id, 'preferred_languages', 'de_DE,es_ES' );

		$expected = array(
			'de_DE',
			'es_ES',
		);

		$this->assertSame( $expected, preferred_languages_get_user_list( $user_id ) );
	}

	/**
	 * @covers ::preferred_languages_get_user_list
	 */
	public function test_get_user_list_user_id_wrong() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );

		$this->assertFalse( preferred_languages_get_user_list( PHP_INT_MAX ) );
	}

	/**
	 * @covers ::preferred_languages_get_user_list
	 */
	public function test_get_user_list_user_instance() {
		$user = self::factory()->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);

		update_user_meta( $user->ID, 'preferred_languages', 'de_DE,es_ES' );

		$expected = array(
			'de_DE',
			'es_ES',
		);

		$this->assertSame( $expected, preferred_languages_get_user_list( $user ) );
	}


	/**
	 * @covers ::preferred_languages_get_user_list
	 */
	public function test_get_user_list_no_current_user() {
		$this->assertFalse( preferred_languages_get_user_list() );
	}

	/**
	 * @covers ::preferred_languages_get_user_list
	 */
	public function test_get_user_list_falls_back_to_locale_user_meta() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'locale', 'de_DE' );

		$expected = array(
			'de_DE',
		);

		$this->assertSame( $expected, preferred_languages_get_user_list() );
	}

	/**
	 * @covers ::preferred_languages_get_site_list
	 */
	public function test_get_site_list() {
		update_option( 'preferred_languages', 'de_DE,es_ES' );

		$expected = array(
			'de_DE',
			'es_ES',
		);

		$this->assertSame( $expected, preferred_languages_get_site_list() );
	}

	/**
	 * @covers ::preferred_languages_get_network_list
	 */
	public function test_get_network_list() {
		update_site_option( 'preferred_languages', 'de_DE,es_ES' );

		$expected = array(
			'de_DE',
			'es_ES',
		);

		$this->assertSame( $expected, preferred_languages_get_network_list() );
	}

	/**
	 * @covers ::preferred_languages_get_list
	 */
	public function test_get_list() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );
		update_option( 'preferred_languages', 'es_ES' );

		$expected = array( 'es_ES' );

		$this->assertSame( $expected, preferred_languages_get_list() );
	}

	/**
	 * @covers ::preferred_languages_get_list
	 */
	public function test_get_list_network() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );
		update_site_option( 'preferred_languages', 'es_ES' );

		$expected = array( 'es_ES' );

		$this->assertSame( $expected, preferred_languages_get_list() );
	}

	/**
	 * @covers ::preferred_languages_get_list
	 */
	public function test_get_list_admin() {
		set_current_screen( 'index.php' );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,en_GB' );
		update_option( 'preferred_languages', 'es_ES' );

		$expected = array(
			'de_DE',
			'en_GB',
		);

		$this->assertSame( $expected, preferred_languages_get_list() );
	}

	/**
	 * @covers ::preferred_languages_get_list
	 */
	public function test_get_list_admin_fallback() {
		set_current_screen( 'index.php' );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'preferred_languages', '' );
		update_option( 'preferred_languages', 'es_ES' );

		$expected = array( 'es_ES' );

		$this->assertSame( $expected, preferred_languages_get_list() );
	}

	/**
	 * @covers ::preferred_languages_filter_locale
	 */
	public function test_get_locale_returns_locale_unchanged() {
		update_option( 'preferred_languages', '' );
		$this->assertSame( get_locale(), get_locale() );
	}

	/**
	 * @covers ::preferred_languages_filter_locale
	 */
	public function test_get_locale_returns_first_preferred_locale() {
		update_option( 'preferred_languages', 'de_CH,fr_FR,es_ES' );
		// Not necessarily de_CH as it depends on preferred_languages_download_language_packs() and get_available_languages().
		$first = explode( ',', get_option( 'preferred_languages' ) )[0];
		$this->assertSame( $first, get_locale() );
	}

	/**
	 * @covers ::preferred_languages_filter_locale
	 * @group ms-required
	 */
	public function test_get_locale_returns_first_preferred_locale_from_network() {
		update_site_option( 'preferred_languages', 'de_CH,fr_FR,es_ES' );
		// Not necessarily de_CH as it depends on preferred_languages_download_language_packs() and get_available_languages().
		$first = explode( ',', get_site_option( 'preferred_languages' ) )[0];
		$this->assertSame( $first, get_locale() );
	}

	/**
	 * @covers ::preferred_languages_register_scripts
	 */
	public function test_register_scripts() {
		preferred_languages_register_scripts();
		$this->assertTrue( wp_script_is( 'preferred-languages', 'registered' ) );
		$this->assertTrue( wp_style_is( 'preferred-languages', 'registered' ) );
	}

	/**
	 * @covers ::preferred_languages_personal_options
	 */
	public function test_personal_options() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$output = get_echo( 'preferred_languages_personal_options', array( wp_get_current_user() ) );

		$this->assertNotEmpty( $output );
	}

	/**
	 * @group ms-excluded
	 * @covers ::preferred_languages_personal_options
	 */
	public function test_personal_options_no_languages() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		add_filter( 'get_available_languages', '__return_empty_array' );

		$output = get_echo( 'preferred_languages_personal_options', array( wp_get_current_user() ) );

		wp_set_current_user( 0 );

		$this->assertNotEmpty( $output );
	}

	/**
	 * @covers ::preferred_languages_personal_options
	 */
	public function test_personal_options_no_capability() {
		$output = get_echo( 'preferred_languages_personal_options', array( wp_get_current_user() ) );

		$this->assertNotEmpty( $output );
	}

	/**
	 * @covers ::preferred_languages_personal_options
	 */
	public function test_personal_options_no_languages_and_no_capability() {
		add_filter( 'get_available_languages', '__return_empty_array' );

		$output = get_echo( 'preferred_languages_personal_options', array( wp_get_current_user() ) );

		$this->assertEmpty( $output );
	}

	/**
	 * @covers ::preferred_languages_filter_user_locale
	 */
	public function test_get_user_local_returns_locale_unchanged() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		update_user_meta( $user_id, 'preferred_languages', '' );

		$this->assertSame( get_user_locale( $user_id ), get_user_locale( $user_id ) );
	}

	/**
	 * @covers ::preferred_languages_filter_user_locale
	 */
	public function test_get_user_locale() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );

		$this->assertSame( 'de_DE', get_user_locale( $user_id ) );
	}

	/**
	 * @covers ::preferred_languages_add_user_meta
	 */
	public function test_add_user_meta_empty_list() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		update_user_meta( $user_id, 'locale', 'de_DE' );
		add_user_meta( $user_id, 'preferred_languages', '' );

		$this->assertSame( '', get_user_meta( $user_id, 'preferred_languages', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'locale', true ) );
	}

	/**
	 * @covers ::preferred_languages_add_user_meta
	 * @group ms-excluded
	 */
	public function test_add_user_meta_downloads_language_packs() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );
		$this->assertSame( 'de_DE,fr_FR', get_user_meta( $user_id, 'preferred_languages', true ) );
		$this->assertSame( 'de_DE', get_user_meta( $user_id, 'locale', true ) );
		$this->assertSame( 1, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_add_user_meta
	 * @group ms-required
	 */
	public function test_add_user_meta_downloads_language_packs_multisite() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );
		$this->assertSame( 'de_DE,fr_FR', get_user_meta( $user_id, 'preferred_languages', true ) );
		$this->assertSame( 'de_DE', get_user_meta( $user_id, 'locale', true ) );
		$this->assertSame( 1, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_user_meta
	 */
	public function test_update_user_meta_download_fails() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		update_user_meta( $user_id, 'preferred_languages', 'it_IT,bg_BG' );

		// Makes wp_download_language_pack() fail early.
		add_filter( 'file_mod_allowed', '__return_false' );

		update_user_meta( $user_id, 'preferred_languages', 'roh' );

		$this->assertSame( 'roh', get_user_meta( $user_id, 'preferred_languages', true ) );
		$this->assertSame( 'roh', get_user_meta( $user_id, 'locale', true ) );
		$this->assertSame( 2, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_add_user_meta
	 * @covers ::preferred_languages_update_user_meta
	 */
	public function test_update_user_meta_downloads_language_packs_again() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,es_ES,fr_FR' );

		$locale = get_user_meta( $user_id, 'preferred_languages', true );
		$this->assertSame( 'de_DE,es_ES,fr_FR', $locale );
		$this->assertSame( 2, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_add_user_meta
	 * @covers ::preferred_languages_update_user_meta
	 */
	public function test_update_user_meta_unchanged_no_prev_value() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );

		$this->assertSame( 1, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_add_user_meta
	 * @covers ::preferred_languages_update_user_meta
	 */
	public function test_update_user_meta_unchanged_downloads_language_packs_again() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		// update_user_meta() bails early if the meta value has not changed
		// and no $prev_value has been provided.
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR' );
		update_user_meta( $user_id, 'preferred_languages', 'de_DE,fr_FR', 'de_DE,fr_FR' );

		$this->assertSame( 2, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_user_meta
	 */
	public function test_update_user_meta_empty_list() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		update_user_meta( $user_id, 'locale', 'de_DE' );
		update_user_meta( $user_id, 'preferred_languages', 'fr_FR,es_ES' );
		update_user_meta( $user_id, 'preferred_languages', '' );

		$this->assertSame( '', get_user_meta( $user_id, 'preferred_languages', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'locale', true ) );
	}

	/**
	 * @covers ::preferred_languages_update_user_meta
	 */
	public function test_update_user_meta() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		update_user_meta( $user_id, 'locale', 'de_DE' );
		update_user_meta( $user_id, 'preferred_languages', 'fr_FR,es_ES,de_DE' );

		$this->assertSame( 'fr_FR,es_ES,de_DE', get_user_meta( $user_id, 'preferred_languages', true ) );
		$this->assertSame( 'fr_FR', get_user_meta( $user_id, 'locale', true ) );
	}

	/**
	 * @covers ::preferred_languages_update_option
	 */
	public function test_add_option_downloads_language_packs() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );
		$this->assertSame( 1, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_option
	 */
	public function test_update_option_downloads_language_packs_again() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );
		update_option( 'preferred_languages', 'de_DE,es_ES,fr_FR' );
		$this->assertSame( 2, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_option
	 * @covers ::preferred_languages_pre_update_option
	 */
	public function test_update_option_unchanged_downloads_language_packs_again() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );
		update_option( 'preferred_languages', 'de_DE,fr_FR' );
		$this->assertSame( 2, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_site_option
	 * @group ms-excluded
	 */
	public function test_update_site_option_single_site() {
		preferred_languages_update_site_option( 'preferred_languages', 'de_DE,fr_FR' );
		$this->assertSame( 0, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_site_option
	 * @group ms-required
	 */
	public function test_add_site_option_downloads_language_packs() {
		update_site_option( 'preferred_languages', 'de_DE,fr_FR' );
		$this->assertSame( 1, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_site_option
	 * @group ms-required
	 */
	public function test_update_site_option_downloads_language_packs_again() {
		update_site_option( 'preferred_languages', 'de_DE,fr_FR' );
		update_site_option( 'preferred_languages', 'de_DE,es_ES,fr_FR' );
		$this->assertSame( 2, $this->download_language_packs_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_site_option
	 * @covers ::preferred_languages_pre_update_option
	 * @group ms-required
	 */
	public function test_update_site_option_unchanged_downloads_language_packs_again() {
		update_site_option( 'preferred_languages', 'de_DE,fr_FR' );
		update_site_option( 'preferred_languages', 'de_DE,fr_FR' );
		$this->assertSame( 2, $this->download_language_packs_action->get_call_count() );
	}

	public function data_test_sanitize_list() {
		return array(
			array( 'de_DE,fr_FR', 'de_DE,fr_FR' ),
			array( ' de_DE , fr_FR ', 'de_DE,fr_FR' ),
			array( '<b>de_DE</b>,fr_FR ', 'de_DE,fr_FR' ),
		);
	}

	/**
	 * @covers ::preferred_languages_sanitize_list
	 * @dataProvider data_test_sanitize_list
	 *
	 * @param string $input
	 * @param string $expected
	 */
	public function test_sanitize_list( $input, $expected ) {
		$actual = preferred_languages_sanitize_list( $input );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 */
	public function test_download_language_packs_no_capability() {
		add_filter( 'user_has_cap', array( $this, 'grant_do_not_allow' ) );

		$actual = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertSameSets( array_intersect( get_available_languages(), array( 'de_DE', 'fr_FR' ) ), $actual );
	}


	/**
	 * @covers ::preferred_languages_download_language_packs
	 */
	public function test_download_language_packs_no_capability_no_available() {
		add_filter( 'get_available_languages', '__return_empty_array' );
		add_filter( 'user_has_cap', array( $this, 'grant_do_not_allow' ) );

		$actual = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertEmpty( $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 * @group ms-excluded
	 */
	public function test_download_language_packs() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		$expected = array( 'de_DE', 'fr_FR' );
		$actual   = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 * @group ms-required
	 */
	public function test_download_language_packs_multisite_no_caps() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		$expected = array( 'de_DE' );
		$actual   = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 * @group ms-required
	 */
	public function test_download_language_packs_multisite() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		grant_super_admin( $user_id );

		$expected = array( 'de_DE', 'fr_FR' );
		$actual   = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 */
	public function test_download_language_packs_available() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		$filter = static function () {
			return array( 'de_DE', 'fr_FR' );
		};

		add_filter( 'get_available_languages', $filter );

		$expected = array( 'de_DE', 'fr_FR' );
		$actual   = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 * @group ms-excluded
	 */
	public function test_download_language_packs_no_available() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		add_filter( 'get_available_languages', '__return_empty_array' );

		$expected = array( 'de_DE', 'fr_FR' );
		$actual   = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 * @group ms-required
	 */
	public function test_download_language_packs_no_available_multisite_no_super_admin() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		add_filter( 'get_available_languages', '__return_empty_array' );

		$actual = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertEmpty( $actual );
	}

	/**
	 * @covers ::preferred_languages_download_language_packs
	 * @group ms-required
	 */
	public function test_download_language_packs_no_available_multisite() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );
		grant_super_admin( $user_id );

		add_filter( 'get_available_languages', '__return_empty_array' );

		$expected = array( 'de_DE', 'fr_FR' );
		$actual   = preferred_languages_download_language_packs( array( 'de_DE', 'fr_FR' ) );

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @covers ::preferred_languages_override_load_textdomain
	 */
	public function test_override_load_textdomain_no_merge() {
		update_option( 'preferred_languages', 'de_DE,es_ES' );

		$actual1 = preferred_languages_override_load_textdomain( false, 'default', '' );
		$actual2 = preferred_languages_override_load_textdomain( true, 'default', '' );

		$this->assertFalse( $actual1 );
		$this->assertTrue( $actual2 );
	}

	/**
	 * @covers ::preferred_languages_override_load_textdomain
	 */
	public function test_override_load_textdomain_no_preferred_locales() {
		add_filter( 'preferred_languages_merge_translations', '__return_true' );

		$this->assertFalse( preferred_languages_override_load_textdomain( false, 'default', '' ) );
		$this->assertTrue( preferred_languages_override_load_textdomain( true, 'default', '' ) );
	}

	/**
	 * @covers ::preferred_languages_override_load_textdomain
	 */
	public function test_override_load_textdomain_already_filtered() {
		add_filter( 'preferred_languages_merge_translations', '__return_true' );
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$filter = static function() {
			return 'es_ES';
		};

		add_filter( 'determine_locale', $filter );

		$actual1 = preferred_languages_override_load_textdomain( false, 'default', '' );
		$actual2 = preferred_languages_override_load_textdomain( true, 'default', '' );

		$this->assertFalse( $actual1 );
		$this->assertTrue( $actual2 );
	}

	/**
	 * @covers ::preferred_languages_override_load_textdomain
	 *
	 * @todo Provide actual translation files to demonstrate merging
	 */
	public function test_override_load_textdomain_no_mofile() {
		add_filter( 'preferred_languages_merge_translations', '__return_true' );
		update_option( 'preferred_languages', 'de_DE,es_ES' );

		$actual = preferred_languages_override_load_textdomain( false, 'default', '' );

		$this->assertFalse( $actual );
	}

	/**
	 * @covers ::preferred_languages_override_load_textdomain
	 */
	public function test_override_load_textdomain_merge() {
		add_filter( 'preferred_languages_merge_translations', '__return_true' );
		update_option( 'preferred_languages', 'es_ES,de_DE' );

		$actual = preferred_languages_override_load_textdomain( false, 'default', WP_LANG_DIR . '/es_ES.mo' );

		$this->assertTrue( $actual );
		$this->assertSame( 'de-DE', __( 'html_lang_attribute' ) );

		// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		$this->assertSame( '[%s] Solicitud de borrado completada', __( '[%s] Erasure Request Fulfilled' ) );
	}

	/**
	 * @covers ::preferred_languages_load_textdomain_mofile
	 */
	public function test_load_textdomain_mofile_no_preferred_locales() {
		$actual = preferred_languages_load_textdomain_mofile( 'foo' );
		$this->assertSame( 'foo', $actual );
	}

	/**
	 * @covers ::preferred_languages_load_textdomain_mofile
	 */
	public function test_load_textdomain_mofile_already_filtered() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$filter = static function() {
			return 'es_ES';
		};

		add_filter( 'determine_locale', $filter );

		$actual = preferred_languages_load_textdomain_mofile( 'foo' );

		$this->assertSame( 'foo', $actual );
	}

	/**
	 * @covers ::preferred_languages_load_textdomain_mofile
	 */
	public function test_load_textdomain_mofile_no_match() {
		update_option( 'preferred_languages', 'fr_FR' );

		$actual = preferred_languages_load_textdomain_mofile( WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo' );

		$this->assertSame( WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo', $actual );
	}

	/**
	 * @covers ::preferred_languages_load_textdomain_mofile
	 */
	public function test_load_textdomain_mofile_non_existent_file() {
		update_option( 'preferred_languages', 'de_DE' );

		$actual = preferred_languages_load_textdomain_mofile( WP_LANG_DIR . '/plugins/internationalized-plugin-roh.mo' );

		$this->assertSame( WP_LANG_DIR . '/plugins/internationalized-plugin-roh.mo', $actual );
	}

	/**
	 * @covers ::preferred_languages_load_textdomain_mofile
	 */
	public function test_load_textdomain_mofile_only() {
		update_option( 'preferred_languages', 'en_GB,de_DE' );

		$actual = preferred_languages_load_textdomain_mofile( WP_LANG_DIR . '/plugins/internationalized-plugin-en_GB.mo' );

		$this->assertSame( WP_LANG_DIR . '/plugins/internationalized-plugin-de_DE.mo', $actual );
	}

	/**
	 * @covers ::preferred_languages_pre_load_script_translations
	 */
	public function test_pre_load_script_translations_no_preferred_locales() {
		$this->assertFalse( preferred_languages_pre_load_script_translations( false, 'file', 'handle', 'default' ) );
		$this->assertSame( '', preferred_languages_pre_load_script_translations( '', 'file', 'handle', 'default' ) );
	}

	/**
	 * @covers ::preferred_languages_pre_load_script_translations
	 */
	public function test_pre_load_script_translations_already_filtered() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$filter = static function() {
			return 'es_ES';
		};

		add_filter( 'determine_locale', $filter );

		$actual1 = preferred_languages_pre_load_script_translations( false, 'file', 'handle', 'default' );
		$actual2 = preferred_languages_pre_load_script_translations( '', 'file', 'handle', 'default' );

		$this->assertFalse( $actual1 );
		$this->assertSame( '', $actual2 );
	}

	/**
	 * @covers ::preferred_languages_pre_load_script_translations
	 */
	public function test_pre_load_script_translations_no_merge() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$actual1 = preferred_languages_pre_load_script_translations( false, 'file', 'handle', 'default' );
		$actual2 = preferred_languages_pre_load_script_translations( '', 'file', 'handle', 'default' );

		$this->assertFalse( $actual1 );
		$this->assertSame( '', $actual2 );
	}

	/**
	 * @covers ::preferred_languages_pre_load_script_translations
	 */
	public function test_pre_load_script_translations_merge_invalid_file() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		add_filter( 'preferred_languages_merge_translations', '__return_true' );

		$actual = preferred_languages_pre_load_script_translations( null, 'file', 'handle', 'default' );

		$this->assertNull( $actual );
	}

	/**
	 * @covers ::preferred_languages_pre_load_script_translations
	 *
	 * @todo Provide actual translation files to demonstrate merging
	 */
	public function test_pre_load_script_translations_merge() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		add_filter( 'preferred_languages_merge_translations', '__return_true' );

		$actual = preferred_languages_pre_load_script_translations(
			null,
			WP_LANG_DIR . '/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json',
			'internationalized-plugin',
			'default'
		);

		$this->assertNotNull( $actual );
		$this->assertNotEmpty( $actual );
		$this->assertStringContainsString( 'translation-revision-data', $actual );
		$this->assertNotNull( json_decode( $actual, true ) );
	}

	/**
	 * @covers ::preferred_languages_load_script_translation_file
	 */
	public function test_load_script_translation_file_no_preferred_locales() {
		$actual = preferred_languages_load_script_translation_file( 'foo' );
		$this->assertSame( 'foo', $actual );
	}

	/**
	 * @covers ::preferred_languages_load_script_translation_file
	 */
	public function test_load_script_translation_file_already_filtered() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$filter = static function() {
			return 'es_ES';
		};

		add_filter( 'determine_locale', $filter );

		$actual = preferred_languages_load_script_translation_file( 'foo' );

		$this->assertSame( 'foo', $actual );
	}

	/**
	 * @covers ::preferred_languages_load_script_translation_file
	 */
	public function test_load_script_translation_file_en_US() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$actual = preferred_languages_load_script_translation_file( WP_LANG_DIR . '/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json' );

		$this->assertSame( WP_LANG_DIR . '/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json', $actual );
	}

	/**
	 * @covers ::preferred_languages_load_script_translation_file
	 */
	public function test_load_script_translation_file_no_match() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$actual = preferred_languages_load_script_translation_file( WP_LANG_DIR . '/plugins/internationalized-plugin-es_ES-2f86cb96a0233e7cb3b6f03ad573be0b.json' );

		$this->assertSame( WP_LANG_DIR . '/plugins/internationalized-plugin-es_ES-2f86cb96a0233e7cb3b6f03ad573be0b.json', $actual );
	}

	/**
	 * @covers ::preferred_languages_settings_field
	 */
	public function test_settings_field() {
		global $wp_settings_fields;

		preferred_languages_settings_field();

		$expected = array(
			'id'       => 'preferred_languages',
			'title'    => '<span id="preferred-languages-label">' . __( 'Site Language', 'preferred-languages' ) . '<span/> <span class="dashicons dashicons-translation" aria-hidden="true"></span>',
			'callback' => 'preferred_languages_display_form',
			'args'     => array(
				'class'    => 'site-preferred-languages-wrap',
				'selected' => preferred_languages_get_site_list(),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $wp_settings_fields['general']['default']['preferred_languages'] );
	}

	/**
	 * @covers ::preferred_languages_settings_field
	 * @group ms-required
	 */
	public function test_settings_field_multisite() {
		global $wp_settings_fields, $wp_settings_sections;

		preferred_languages_settings_field();

		$expected_field = array(
			'id'       => 'preferred_languages',
			'title'    => '<span id="preferred-languages-label">' . __( 'Default Language', 'preferred-languages' ) . '<span/> <span class="dashicons dashicons-translation" aria-hidden="true"></span>',
			'callback' => 'preferred_languages_display_form',
			'args'     => array(
				'class'    => 'network-preferred-languages-wrap',
				'selected' => preferred_languages_get_site_list(),
			),
		);

		$this->assertNotNull( $wp_settings_sections );
		$this->assertArrayHasKey( 'preferred_languages_network_settings', $wp_settings_sections );
		$this->assertArrayHasKey( 'preferred_languages', $wp_settings_sections['preferred_languages_network_settings'] );
		$this->assertSame( 'preferred_languages', $wp_settings_sections['preferred_languages_network_settings']['preferred_languages']['id'] );
		$this->assertSame( '', $wp_settings_sections['preferred_languages_network_settings']['preferred_languages']['title'] );
		$this->assertSame( '__return_empty_string', $wp_settings_sections['preferred_languages_network_settings']['preferred_languages']['callback'] );

		$this->assertEqualSetsWithIndex( $expected_field, $wp_settings_fields['preferred_languages_network_settings']['preferred_languages']['preferred_languages'] );
	}

	/**
	 * @covers ::preferred_languages_network_settings_field
	 * @group ms-required
	 */
	public function test_network_settings_field() {
		$actual = get_echo( 'preferred_languages_network_settings_field' );

		$this->assertStringContainsString( '<span id="preferred-languages-label">' . __( 'Default Language', 'preferred-languages' ) . '<span/> <span class="dashicons dashicons-translation" aria-hidden="true"></span>', $actual );
	}

	/**
	 * @covers ::preferred_languages_display_form
	 */
	public function test_display_form() {
		get_echo( 'preferred_languages_display_form' );
		$this->assertTrue( wp_script_is( 'preferred-languages' ) );
		$this->assertTrue( wp_style_is( 'preferred-languages' ) );
	}

	/**
	 * @covers ::preferred_languages_display_form
	 */
	public function test_display_form_show_option_site_default() {
		$actual = get_echo(
			static function() {
				preferred_languages_display_form(
					array(
						'show_option_site_default' => true,
					)
				);
			}
		);

		$this->assertStringContainsString( 'Falling back to Site Default.', $actual );
	}

	/**
	 * @covers ::preferred_languages_display_form
	 */
	public function test_display_form_show_option_en_US() {
		$actual = get_echo(
			static function() {
				preferred_languages_display_form(
					array(
						'show_option_site_default' => true,
					)
				);
			}
		);

		$this->assertStringContainsString( 'English (United States)', $actual );
	}

	/**
	 * @covers ::preferred_languages_display_form
	 * @group ms-excluded
	 */
	public function test_display_form_missing_language_packs_warning() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user_id );

		add_filter( 'get_available_languages', '__return_empty_array' );

		$actual = get_echo(
			static function() {
				preferred_languages_display_form( array( 'selected' => array( 'de_DE', 'fr_FR' ) ) );
			}
		);

		$this->assertStringContainsString( 'Some of the languages are not installed.', $actual );
	}

	/**
	 * @covers ::preferred_languages_update_network_settings
	 *
	 * @group ms-excluded
	 */
	public function test_update_network_settings_single_site() {
		$mock_action = new MockAction();

		add_action( 'wp_verify_nonce_failed', array( $mock_action, 'action' ) );

		$_POST['preferred_languages_network_settings_nonce'] = 'foo';
		preferred_languages_update_network_settings();

		$this->assertSame( 0, $mock_action->get_call_count() );
	}

	/**
	 * @covers ::preferred_languages_update_network_settings
	 *
	 * @group ms-required
	 */
	public function test_update_network_settings_no_nonce() {
		update_site_option( 'preferred_languages', 'de_DE,es_ES' );
		preferred_languages_update_network_settings();

		$this->assertSame( 'de_DE,es_ES', get_site_option( 'preferred_languages' ) );
	}

	/**
	 * @covers ::preferred_languages_update_network_settings
	 *
	 * @group ms-required
	 */
	public function test_update_network_settings_wrong_nonce() {
		$_POST['preferred_languages_network_settings_nonce'] = 'foo';
		update_site_option( 'preferred_languages', 'de_DE,es_ES' );
		preferred_languages_update_network_settings();

		$this->assertSame( 'de_DE,es_ES', get_site_option( 'preferred_languages' ) );
	}

	/**
	 * @covers ::preferred_languages_update_network_settings
	 *
	 * @group ms-required
	 */
	public function test_update_network_settings_no_post_data() {
		$_POST['preferred_languages_network_settings_nonce'] = wp_slash( wp_create_nonce( 'preferred_languages_network_settings' ) );
		update_site_option( 'preferred_languages', 'de_DE,es_ES' );
		preferred_languages_update_network_settings();

		$this->assertSame( 'de_DE,es_ES', get_site_option( 'preferred_languages' ) );
	}

	/**
	 * @covers ::preferred_languages_update_network_settings
	 *
	 * @group ms-required
	 */
	public function test_update_network_settings_post_data_sanitized() {
		$_POST['preferred_languages_network_settings_nonce'] = wp_slash( wp_create_nonce( 'preferred_languages_network_settings' ) );
		$_POST['preferred_languages']                        = wp_slash( 'es_ES , <b>en_GB</b>' );
		update_site_option( 'preferred_languages', 'de_DE,fr_FR' );
		preferred_languages_update_network_settings();

		$this->assertSame( 'es_ES,en_GB', get_site_option( 'preferred_languages' ) );
	}

	/**
	 * @covers ::preferred_languages_filter_gettext
	 */
	public function test_filter_gettext_default() {
		$actual = preferred_languages_filter_gettext( 'Hello World', 'Hello World', 'default' );
		$this->assertSame( 'Hello World', $actual );
	}

	/**
	 * @covers ::preferred_languages_filter_gettext
	 */
	public function test_filter_gettext_plugin_no_preferred_languages() {
		$actual = preferred_languages_filter_gettext( 'This is a dummy plugin', 'This is a dummy plugin', 'internationalized-plugin' );
		$this->assertSame( 'This is a dummy plugin', $actual );
	}

	/**
	 * @covers ::preferred_languages_filter_gettext
	 */
	public function test_filter_gettext_plugin_already_filtered() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$filter = static function() {
			return 'es_ES';
		};

		add_filter( 'determine_locale', $filter );

		$actual = preferred_languages_filter_gettext( 'This is a dummy plugin', 'This is a dummy plugin', 'internationalized-plugin' );

		$this->assertSame( 'This is a dummy plugin', $actual );
	}

	/**
	 * @covers ::preferred_languages_filter_gettext
	 */
	public function test_filter_gettext_plugin_already_translated() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$actual = preferred_languages_filter_gettext( 'Das ist ein Dummy Plugin', 'This is a dummy plugin', 'internationalized-plugin' );

		$this->assertSame( 'Das ist ein Dummy Plugin', $actual );
	}

	/**
	 * @covers ::preferred_languages_filter_gettext
	 */
	public function test_filter_gettext_plugin_filter() {
		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$this->assertSame( 'Das ist ein Dummy Plugin', __( 'This is a dummy plugin', 'internationalized-plugin' ) );
	}

	/**
	 * @covers ::preferred_languages_filter_gettext
	 */
	public function test_filter_gettext_plugin_custom_path() {
		update_option( 'preferred_languages', 'fr_FR,de_DE' );

		require_once WP_PLUGIN_DIR . '/custom-internationalized-plugin/custom-internationalized-plugin.php';

		$actual = custom_i18n_plugin_test();

		$this->assertSame( 'Das ist ein Dummy Plugin', $actual );
	}

	/**
	 * @covers ::preferred_languages_filter_gettext
	 */
	public function test_filter_gettext_plugin_custom_path_locale_switching() {
		update_option( 'preferred_languages', 'fr_FR,de_DE,es_ES' );

		require_once WP_PLUGIN_DIR . '/custom-internationalized-plugin/custom-internationalized-plugin.php';

		switch_to_locale( 'de_DE' );
		$actual_de = custom_i18n_plugin_test();
		switch_to_locale( 'es_ES' );
		$actual_es = custom_i18n_plugin_test();
		restore_current_locale();

		$this->assertSame( 'Das ist ein Dummy Plugin', $actual_de );
		$this->assertSame( 'Este es un plugin dummy', $actual_es );
	}

	/**
	 * @covers ::preferred_languages_filter_debug_information
	 */
	public function test_filter_debug_information() {
		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}

		update_option( 'preferred_languages', 'de_DE,fr_FR' );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		update_user_meta( $user_id, 'preferred_languages', 'fr_FR,es_ES' );

		wp_set_current_user( $user_id );

		$site_list = preferred_languages_get_site_list();
		$user_list = preferred_languages_get_user_list();
		$data      = WP_Debug_Data::debug_data();
		$this->assertSame( implode( ', ', $site_list ), $data['wp-core']['fields']['site_language']['value'] );
		$this->assertSame( implode( ', ', $user_list ), $data['wp-core']['fields']['user_language']['value'] );
	}
}
