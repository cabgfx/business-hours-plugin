<?php
class BusinessHours {

	/**
	 * @var MZASettings
	 */
	public $settings;
	private static $instance;
	private $path;
	private $url;


	public function  __construct() {
		$this->path = trailingslashit( dirname( __FILE__ ) );
		$this->url  = trailingslashit( plugins_url( '', __FILE__ ) );

		$this->register_settings();
		add_shortcode( 'businesshours', array( $this, 'shortcode' ) );
		add_shortcode( 'businesshoursweek', array( $this, 'shortcode_table' ) );
	}

	public function enqueue_resources() {
		wp_enqueue_style( 'BusinessHoursStyle', $this->url . 'resources/style.css' );
		wp_enqueue_script( 'BusinessHoursScript', $this->url . 'resources/script.js', array( 'jquery' ) );
	}


	public function shortcode( $atts, $content = null ) {

		$closed = "";
		extract( shortcode_atts( array( 'closed'      => 'Closed' ), $atts ) );

		if ( $content ) {

			$day = $this->get_day_using_timezone();

			$id = key( $day );

			$open    = $this->settings->get_setting( $id, "open" );
			$close   = $this->settings->get_setting( $id, "close" );
			$working = $this->settings->get_setting( $id, "working" );

			if ( $working == "true" ) {
				$content = str_replace( "{{TodayOpen}}", $open, $content );
				$content = str_replace( "{{TodayClose}}", $close, $content );
			} else {
				$content = $closed;
			}
		}
		return $content;
	}

	public function shortcode_table( $atts ) {

		$collapsible = 'false';
		extract( shortcode_atts( array( 'collapsible' => 'false', ), $atts ) );

		if ( strtolower( $collapsible ) == "true" ) {
			$collapsible = true;
		}
		if ( strtolower( $collapsible ) == "false" ) {
			$collapsible = false;
		}

		if ( $collapsible ) {
			$this->enqueue_resources();
		}
		return $this->get_table( $collapsible );
	}


	public function get_day_using_timezone() {

		if ( get_option( 'timezone_string' ) ) {
			$zone      = new DateTimeZone( get_option( 'timezone_string' ) );
			$timestamp = new DateTime( 'now', $zone );
			$timestamp = strtotime( $timestamp->date );
		} else {
			$offset    = get_option( 'gmt_offset' );
			$offset    = $offset * 60 * 60;
			$timestamp = time() + $offset;
		}

		$arr = array( strtolower( gmdate( 'l', $timestamp ) )  => ucwords( date_i18n( 'l', $timestamp ) ) );
		return $arr;
	}

	private function _get_week_days() {
		$timestamp = strtotime( 'next Sunday' );
		$days      = array();
		for ( $i = 0; $i < 7; $i++ ) {

			$days[]    = array( strtolower( gmdate( 'l', $timestamp ) )  => ucwords( date_i18n( 'l', $timestamp ) ) );
			$timestamp = strtotime( '+1 day', $timestamp );
		}
		return $days;
	}

	public function show_table( $collapsible_link = true ) {
		echo $this->get_table( $collapsible_link );
	}

	/**
	 * @param bool $collapsible_link
	 *
	 * @filter business-hours-closed-text
	 * @filter business-hours-open-hour
	 * @filter business-hours-close-hour
	 * @filter business-hours-is-open-today
	 *
	 * @return string
	 */
	public function get_table( $collapsible_link = true ) {

		$ret = "";

		if ( $collapsible_link ) {
			$ret .= '<a class="business_hours_collapsible_handler" href="#">' . __( "[Show all days]", "business-hours" ) . '</a>';
			$ret .= '<div class="business_hours_collapsible">';
		}

		$days = $this->_get_week_days();

		$ret .= "<table width='100%'>";
		$ret .= "<tr><th>" . __( "Day", "business-hours" ) . "</th><th  class='business_hours_table_heading'>" . __( "Open", "business-hours" ) . "</th><th  class='business_hours_table_heading'>" . __( "Close", "business-hours" ) . "</th></tr>";
		foreach ( $days as $day ) {

			$id   = key( $day );
			$name = $day[$id];

			$open    = apply_filters( "business-hours-open-hour", business_hours()->settings->get_setting( $id, "open" ), $id );
			$close   = apply_filters( "business-hours-close-hour", business_hours()->settings->get_setting( $id, "close" ), $id );
			$working = apply_filters( "business-hours-is-open-today", business_hours()->settings->get_setting( $id, "working" ), $id );


			$ret .= "<tr>";
			$ret .= "<td class='business_hours_table_day'>" . ucwords( $name ) . "</td>";

			if ( $working == "true" ) {
				$ret .= "<td class='business_hours_table_open'>" . ucwords( $open ) . "</td>";
				$ret .= "<td class='business_hours_table_close'>" . ucwords( $close ) . "</td>";

			} else {

				$closed_text = apply_filters( "business-hours-closed-text", __( "Closed", "business-hours" ) );
				$ret .= "<td class='business_hours_table_closed' colspan='2' align='center'>" . $closed_text . "</td>";

			}

			$ret .= "</tr>";
		}
		$ret .= "</table>";
		if ( $collapsible_link ) {
			$ret .= '</div>';
		}

		return $ret;
	}


	private function register_settings() {

		$days     = $this->_get_week_days();
		$sections = array();

		foreach ( $days as $day ) {
			$id            = key( $day );
			$name          = $day[$id];
			$sections[$id] = array( "title"  => $name,
			                        "business-hours",
			                        "fields" => array( "working" => array( "title"   => sprintf( __( "Is it open on %s?", "business-hours" ), $name ),
			                                                               "type"    => "checkbox",
			                                                               "options" => array( "true" => "" ) ),
			                                           "open"    => array( "title"       => __( "Open", "business-hours" ) . ":",
			                                                               "type"        => "time",
			                                                               "description" => "HH:MM"

			                                           ),
			                                           "close"   => array( "title"       => __( "Close", "business-hours" ) . ":",
			                                                               "type"        => "time",
			                                                               "description" => "HH:MM"

			                                           ) ) );

		}

		$sections["support"] = array( "title"  => __( "Support", "business-hours" ),
		                              "fields" => array( "mzaweb" => array( "title" => __( "Bugs? Questions? Suggestions?", "business-hours" ),
		                                                                    "type"  => "support",
		                                                                    "email" => "support@mzaweb.com" ) ) );

		$this->settings                    = new MZASettings( "working-hours", 'options-general.php', $sections );
		$this->settings->settingsPageTitle = __( "Business Hours Settings", "business-hours" );
		$this->settings->settingsLinkTitle = __( "Business Hours", "business-hours" );

		$this->settings->customJS .= "jQuery('#working-hours_settings_form input:checkbox').each(function() {
            index = jQuery(this).index('#working-hours_settings_form input:checkbox') * 2;

            if (this.checked){

                jQuery('.field-row-time').eq(index).show();
                jQuery('.field-row-time').eq(index+1).show();
            }else{
                jQuery('.field-row-time').eq(index).hide();
                jQuery('.field-row-time').eq(index+1).hide();
            }
        });";

		$this->settings->customJS .= "jQuery('#working-hours_settings_form input:checkbox').change(function() {
            index = jQuery(this).index('#working-hours_settings_form input:checkbox') * 2;

            if (this.checked){

                jQuery('.field-row-time').eq(index).show();
                jQuery('.field-row-time').eq(index+1).show();
            }else{
                jQuery('.field-row-time').eq(index).hide();
                jQuery('.field-row-time').eq(index+1).hide();
            }
        });";
	}

	/**
	 * Allows users to overide views templates.
	 *
	 * It'll first check if the given $template is present in a business-hours folder in the user's theme.
	 * If the user didn't create an overide, it'll load the default file from this plugin's views template.
	 *
	 * @param $template
	 *
	 * @return string
	 */
	public function locate_view( $template ) {
		if ( $theme_file = locate_template( array( 'business-hours/' . $template ) ) ) {
			$file = $theme_file;
		} else {
			$file = $this->path . 'views/' . $template;
		}
		return apply_filters( 'business-hours-view-template', $file, $template );
	}


	/**
	 * @static
	 * @return BusinessHours
	 */
	public static function instance() {
		if ( !isset( self::$instance ) ) {
			$className      = __CLASS__;
			self::$instance = new $className;
		}
		return self::$instance;
	}

}

if ( !function_exists( 'business_hours' ) ) {
	/**
	 * @return BusinessHours
	 */
	function business_hours() {
		return BusinessHours::instance();
	}
}