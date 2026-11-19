<?php
// phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Only static images from plugin folder are being added below.
if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * For all copyright, version, etc. information, please see the main plugin file
 * Gets invoked during admin_menu
 * http://codex.wordpress.org/Creating_Options_Pages
 */
class UpdraftPlusAddOns_Options2 {

	public $slug;

	public $title;

	public $mother;

	// Object with at least get_option(), update_option() and addons_admin_url() methods
	private $options;

	/**
	 * Plugin update URL
	 *
	 * @var String
	 */
	private $plugin_update_url;

	/**
	 * Updated JavaScript code.
	 *
	 * @var String
	 */
	private $update_js;

	public function __construct($slug, $title, $mother) {
		global $updraftplus_addons2;
		$this->options = $updraftplus_addons2;
		$this->slug = $slug;
		$this->title = $title;
		$this->mother = $mother;

		// We are called in admin_menu
		// $this->options_menu();

		// New actions to output the tab title and content
		add_filter('updraftplus_addonstab_content', array($this, 'updraftplus_addonstab_content'));
		add_filter('updraftplus_com_login_options', array($this, 'updraftplus_com_login_options'));

		add_action('admin_init', array($this, 'show_admin_notices'));

		add_filter((is_multisite() ? 'network_admin_' : '').'plugin_action_links', array($this, 'action_links'), 10, 2);
		add_action('updraftplus_display_connection_status_notice', array($this, 'print_connection_status_html_wrapper'));
	}

	/**
	 * Return the content of the addons tab
	 *
	 * @param string $content - the addons tab content
	 * @return string - the addons tab content
	 */
	public function updraftplus_addonstab_content($content) {
		ob_start();
		$this->options_printpage();
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escape late; we don't do it here because we want it to happen at caller's side
		return ob_get_clean();
	}

	/**
	 * Registers any admin page notices. Runs upon admin_init.
	 */
	public function show_admin_notices() {
		global $pagenow, $plugin_page;

		if ((is_multisite() && 'settings.php' == $pagenow) || (!is_multisite() && 'options-general.php' == $pagenow) && (UDADDONS2_PAGESLUG == $plugin_page || $this->slug == $plugin_page)) {
			$updates_available = get_site_transient('update_plugins');
			global $updraftplus_addons2;
			if (is_object($updates_available) && isset($updates_available->response) && isset($updraftplus_addons2->plug_updatechecker) && isset($updraftplus_addons2->plug_updatechecker->pluginFile) && isset($updates_available->response[$updraftplus_addons2->plug_updatechecker->pluginFile])) {
				$file = $updraftplus_addons2->plug_updatechecker->pluginFile;
				$this->plugin_update_url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&updraftplus_noautobackup=1&plugin=').$file, 'upgrade-plugin_'.$file);
				add_action('all_admin_notices', array($this, 'show_admin_warning_update'));
			}
		}
	}

	/**
	 * Echoes a div with a WP dashboard admin message in it
	 *
	 * @param String $message - the message text (this is unescaped string which allows HTML tags to be included but only tags that are allowed by wp_kses_post())
	 * @param String $class   - the CSS class for the div
	 */
	private function show_admin_warning($message, $class = "updated") {
		echo '<div class="'.esc_attr($class).'">'."<p>".wp_kses_post($message)."</p></div>";
	}

	/**
	 * Show an administrative warning about the available updates of UpdraftPlus
	 */
	public function show_admin_warning_update() {
		global $updraftplus_addons2;
		if ($updraftplus_addons2->connected) {
			$msg = '<a id="updraftaddons_updatewarning" href="'.$this->plugin_update_url.'">'.esc_html__('An update is available for UpdraftPlus - please follow this link to get it.', 'updraftplus').'</a>';
		} else {
			$msg = '<a id="updraftaddons_updatewarning" href="'.esc_url(admin_url($this->options->addons_admin_url())).'">'.esc_html__('An update is available for UpdraftPlus - please connect here to gain access to it.', 'updraftplus').'</a>';
		}
		$this->show_admin_warning($msg);
	}

	/**
	 * This function will return the saved options and if there are none returns the default options passed in.
	 *
	 * @param array $default_options - an array that includes the default options
	 *
	 * @return array                 - returns an array of options
	 */
	public function updraftplus_com_login_options($default_options) {
		$options = $this->options->get_option(UDADDONS2_SLUG.'_options');
		return is_array($options) ? $options : $default_options;
	}

	/**
	 * This is the function outputting the HTML for our options page
	 */
	public function options_printpage() {
		if (!UpdraftPlus_Options::user_can_manage()) wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'updraftplus'));

		echo "\t<div class=\"wrap updraft-account-connection\">\n";

		global $updraftplus_addons2, $updraftplus_admin, $updraftplus;

		if ('updraftplus' != basename(dirname(dirname(__FILE__)))) {
			echo '<div class="error below-h2" style="font-size: 120%;"><p><strong>'.esc_html__('Error', 'updraftplus').':</strong> ';
			/* translators: 1: WordPress plugins folder path, 2: Current (non-default) plugin folder name, 3: Expected default plugin folder name */
			echo sprintf(esc_html__("You have installed this plugin in your plugins folder (%1\$s) with a non-default name %2\$s which is different to %3\$s.", 'updraftplus'), esc_html(WP_PLUGIN_DIR), '<strong>'.esc_html(basename(dirname(dirname(__FILE__)))).'</strong>', '<strong>updraftplus</strong>');
			echo ' '.esc_html__('This is incompatible with WordPress\'s updates mechanism; you will not be able to receive updates.', 'updraftplus').'</p></div>';
		}

		if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
			/* translators: %s: WP_HTTP_BLOCK_EXTERNAL enclosed in <strong> tags. */
			echo '<div class="notice inline"><p>'.sprintf(esc_html__('Please make sure that %s is not set to "true" in your wp-config file - this ensures UpdraftPlus can connect and update.', 'updraftplus'), '<strong>WP_HTTP_BLOCK_EXTERNAL</strong>').'</p></div>';
		}

		$wp_http = new WP_Http();
		if (is_callable(array($wp_http, 'block_request')) && $wp_http->block_request($updraftplus_addons2->url)) {
			/* translators: 1: Domain name (updraftplus.com), 2: WordPress constant name (WP_ACCESSIBLE_HOSTS) */
			echo '<div class="notice inline"><p>'.sprintf(esc_html__('Please list %1$s in the %2$s constant.', 'updraftplus'), '<strong>updraftplus.com</strong>', '<strong>WP_ACCESSIBLE_HOSTS</strong>').' ';
			/* translators: %s: Plugin name (UpdraftPlus) */
			echo sprintf(esc_html__('This ensures %s can connect and update.', 'updraftplus'), 'UpdraftPlus').'</p></div>';
		}

		do_action('updraftplus_display_connection_status_notice');

		if (UpdraftPlus_Options::get_updraft_option('updraftplus_com_and_udc_connection_success', false)) {
			UpdraftPlus_Options::delete_updraft_option('updraftplus_com_and_udc_connection_success');
			echo '<div class="updated below-h2">';
			echo '<p>';
			echo esc_html__('You successfully logged in to UpdraftPlus and connected this site to UpdraftCentral Cloud.', 'updraftplus');
			echo '<br> <a href="https://teamupdraft.com/my-account/updraftcentral/" target="_blank">'.esc_html__('Go to your UpdraftCentral Cloud dashboard', 'updraftplus').'</a>';
			echo '</p>';
			echo '</div>';
		}

		if (!$updraftplus_addons2->connected) $updraftplus_admin->build_credentials_form(UDADDONS2_SLUG, true, false);

		$ourpageslug = UDADDONS2_PAGESLUG;

		$href = UpdraftPlus_Options::admin_page_url();

		if ($updraftplus_addons2->connected && count($updraftplus_addons2->unclaimed_available) > 0) {
			$nonce = wp_create_nonce('udmanager-nonce');
			$pleasewait = __('Please wait whilst we make the claim...', 'updraftplus');
			$notgranted = __('Claim not granted - perhaps you have already used this purchase somewhere else, or your paid period for downloading from teamupdraft.com has expired?', 'updraftplus');
			$notgrantedlogin = __('Claim not granted - your account login details are incorrect.', 'updraftplus').' '.__('Please reconnect by re-entering your email address and password, then try claiming again.', 'updraftplus');
			$ukresponse = __('An unknown response was received.', 'updraftplus').' '.__('Response was:', 'updraftplus');
			$addon_installed = __('The claim and installation was successful.', 'updraftplus').' '.__('You can now use your purchase!', 'updraftplus');
			?>
		<script type="text/javascript">
			function udm_claim(key) {
				if (jQuery('#addon-'+key).children('.addon-activation-notice').length) {
					return false;
				}
				var data = {
					action: 'udaddons_claimaddon',
					nonce: '<?php echo $nonce; ?>',
					key: key
				};
				
				jQuery('#addon-'+key).prepend('<div class="addon-activation-notice updated" style="border: 1px solid; padding: 10px; margin-top: 10px; margin-bottom: 10px; position: absolute; z-index:99; "><strong><?php echo esc_html($pleasewait);?></strong></div>');
				
				jQuery.post(ajaxurl, data, function(resp) {
				
					var response_code;
					var addons_written = false;

					jQuery('#addon-'+key).find('div.addon-activation-notice').hide();
				
					try {
						response = ud_parse_json(resp);
						response_code = response.hasOwnProperty('code') ? response.code : 'UNKNOWN';
						addons_written = response.hasOwnProperty('addons_written') ? response.addons_written : false;
					} catch (e) {
						console.log(e);
						response_code = 'PARSE_ERROR';
					}
					
					if ('ERR' == response_code) {
						alert("<?php echo esc_js($notgranted); ?>");
					} else if ('OK' == response_code) {
						// We used to force udm_refresh to 1, before (Oct 2017) the possibility that there was already an updates result in the claim response
						var new_location = '<?php echo esc_url($href);?>';
						// Aug 2018: The check updates process does not refresh the user_addons list, so the plugin does not recognise the claim was granted. We need to force a refresh when a claim is activated
						if (addons_written) {
							alert("<?php echo esc_js($addon_installed);?>");
						}
						// Still do the page refresh so that the version number + other UI elements update
						new_location += '?page=<?php echo esc_js($ourpageslug); ?>&tab=addons&udm_refresh=1';
						window.location.href = new_location;
					} else if ('BADAUTH' == response_code) {
						alert("<?php echo esc_js($notgrantedlogin); ?>");
						jQuery('html, body').animate({scrollTop: jQuery('#updraftplus-addons_options_email').offset().top-100}, 500);
					} else {
						alert("<?php echo esc_js($ukresponse);?> "+response);
					}
				});
				
				return false;
			}
		</script>
			<?php
		}

		$addons = $updraftplus_addons2->available_addons;

		$this->plugin_update_url = 'update-core.php';
		// Can we get a direct update URL?
		$updates_available = get_site_transient('update_plugins');

		if (is_object($updates_available) && isset($updates_available->response) && isset($updraftplus_addons2->plug_updatechecker) && isset($updraftplus_addons2->plug_updatechecker->pluginFile) && isset($updates_available->response[$updraftplus_addons2->plug_updatechecker->pluginFile])) {
			$file = $updraftplus_addons2->plug_updatechecker->pluginFile;
			$this->plugin_update_url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&updraftplus_noautobackup=1&plugin=').$file, 'upgrade-plugin_'.$file);
			$this->update_js = true;

		}

		$first = '';
		$second = '';
		$third = '';

		if (is_array($addons)) {
			// Making $addons['installed'] = true manually.
			// FIX: It's the "All add-ons" link that was always show as available for activation, because there's no particular "all-addons" add-on.
			$does_all_addons_installed = true;
			foreach ($addons as $key => $addon) {
				if ('all' == $key) continue;
				if (empty($addon['installed'])) {
					$does_all_addons_installed = false;
					break;
				}
			}
			if ($does_all_addons_installed) {
				$addons['all']['installed'] = true;
				if (empty($addons['all']['name'])) {
					$addons['all']['name'] = 'UpdraftPlus Premium';
					$addons['all']['shopurl'] = $updraftplus->get_url('premium');
					$addons['all']['description'] = 'UpdraftPlus Premium';
				}
			}

			foreach ($addons as $key => $addon) {
				// check if premium add-on is purchased. If it is then don't display other add-ons.
				if (!empty($addons['all']['installed']) && ('all' != $key)) continue;
				$latestversion = empty($addon['latestversion']) ? false : $addon['latestversion'];
				$installedversion = empty($addon['installedversion']) ? false : $addon['installedversion'];
				$installed = (empty($addon['installed']) && false == $installedversion) ? false : $addon['installed'];
				$unclaimed = (isset($updraftplus_addons2->unclaimed_available[$key])) ? $updraftplus_addons2->unclaimed_available[$key] : false;
				$is_assigned = (isset($updraftplus_addons2->assigned_addons[$key])) ? $updraftplus_addons2->assigned_addons[$key] : false;
				$box = $this->addonbox($key, $addon['name'], $addon['shopurl'], $addon['description'], trim($installedversion), trim($latestversion), $installed, $unclaimed, $is_assigned, $updraftplus_addons2->have_all_addons);
				if ($is_assigned) {
					$first .= $box;
				} elseif (!empty($unclaimed)) {
					$second .= $box;
				} else {
					$third .= $box;
				}
			}
		} else {
			$first = "<em>".esc_html__('An error occurred when trying to retrieve your add-ons.', 'updraftplus')."</em>";
		}

		$addon_boxes = $first.$second.$third;
		// Hide header if no addon box is visible or no error trying to retrieve add-ons.
		if (!empty($addon_boxes)) {
			echo '<h3 style="clear:left; margin-top: 10px;">'.esc_html__('UpdraftPlus Premium', 'updraftplus').'</h3><div>';
			echo $addon_boxes;// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contains HTML <a> tags that has inline onclick event
			echo '</div>';
		}

		if ($this->update_js) {
		?>
			<script>
				jQuery(function() {
						jQuery('#updraftaddons_updatewarning').html('<?php echo esc_js(__('A new update for UpdraftPlus is available - follow this link to get it', 'updraftplus')); ?>');
					}
				);
			</script>
		<?php
		}

		// TODO: Show their support package, if any - ?
		if (is_array($updraftplus_addons2->user_support)) {
			// Keys:
		}

		echo '<h3>'.esc_html__('UpdraftPlus Support', 'updraftplus').'</h3>
<ul>
<li style="list-style:disc inside;">'.esc_html__('Need to get support?', 'updraftplus').' <a aria-label="'.esc_html__('Need to get support?', 'updraftplus').esc_html__('Go here', 'updraftplus').'" target="_blank" href="https://teamupdraft.com/support/?utm_source=udp-plugin&utm_medium=referral&utm_campaign=paac&utm_content=go-here&utm_creative_format=text">'.esc_html__('Go here', 'updraftplus')."</a>.</li>
</ul>";

		if ($updraftplus_addons2->connected) {
			echo "<hr>";
			$updraftplus_admin->build_credentials_form(UDADDONS2_SLUG, true, false);
		}

		echo '</div>';

	}

	private function addonbox($key, $name, $shopurl, $description, $installedversion, $latestversion = false, $installed = false, $unclaimed = false, $is_assigned = false, $have_all = false) {
		global $updraftplus_addons2;
		$urlbase = UPDRAFTPLUS_URL.'/images/addons-images';
		$mother = $this->mother;
		$extra_description = '';

		// Remove pCloud from the individual add-ons list.
		if ('pcloud' == $key) {
			return;
		} elseif ('all' == $key) {
			$name = esc_html('Get every feature of UpdraftPlus Premium', 'updraftplus');
			$description = esc_html__('Reduce server load by backing up only incremental changes made to your website or by backing up at set times, like overnight when server resources are high.', 'updraftplus');
			$extra_description = esc_html(__('Back up automatically before updates and protect more of your hard work by backing up more files and more databases.', 'updraftplus').' '.__('Anonymise personal backup data, encrypt the database and more.', 'updraftplus'));
		}

		if ($installed && ($is_assigned || ($have_all && 'all' != $key))) {
			if ('all' === $key) return;
			$blurb = "<p>";
			$preblurb = "<div style=\"float:right;padding-top:10px;\"><img title=\"".esc_html__('You\'ve got it', 'updraftplus')."\" src=\"$urlbase/$key.png\" width=\"100\" height=\"100\" alt=\"".esc_html__("You've got it", 'updraftplus')."\"></div>";
			if ('all' != $key) {
				/* translators: %s: Currently installed version of Add-on. */
				$blurb .= sprintf(esc_html__('Your version: %s', 'updraftplus'), $installedversion);
				if (!empty($latestversion) && $latestversion == $installedversion) {
					$blurb .= " (".esc_html__('latest', 'updraftplus').')';
				} elseif (!empty($latestversion) && version_compare($latestversion, $installedversion, '>')) {
					$blurb .= " (".esc_html__('latest', 'updraftplus').": $latestversion - <a href=\"".$this->plugin_update_url."\">update</a>)";
				} else {
					$blurb .= " ".esc_html__('(apparently a pre-release or withdrawn release)', 'updraftplus');
				}
			}
			$blurb .= "</p>";
		} else {
			if ('all' == $key && $is_assigned && false === $unclaimed) return;
			if ($have_all && 'all' != $key) {
				$blurb = '<p><strong>'.esc_html__('Available for this site (via your all-addons purchase)', 'updraftplus').' - <a class="updraftplus-addon-assigned-not-installed" href="'.$this->plugin_update_url.'">'.esc_html__('please follow this link to update the plugin in order to get it', 'updraftplus').'</a></strong></p>';
				$preblurb = "<div style=\"border: 2px solid #189c5f;padding: 10px;width: 72px;height: 72px;border-radius: 5px;position: relative;background: #1a9c5e0f;\"><img style=\"-webkit-filter: grayscale(100%);filter: grayscale(100%);width: 56px;position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);\" src=\"$urlbase/$key.png\"></div>";
			} elseif ($is_assigned) {
				$blurb = '<p><strong>'.esc_html__('Assigned to this site', 'updraftplus').' - <a class="updraftplus-addon-assigned-not-installed" href="'.$this->plugin_update_url.'">'.esc_html__('please follow this link to update the plugin in order to activate it', 'updraftplus').'</a></strong></p>';
				$preblurb = "<div style=\"border: 2px solid #189c5f;padding: 10px;width: 72px;height: 72px;border-radius: 5px;position: relative;background: #1a9c5e0f;\"><img style=\"-webkit-filter: grayscale(100%);filter: grayscale(100%);width: 56px;position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);\" src=\"$urlbase/$key.png\"></div>";
			} elseif (is_array($unclaimed)) {
				// Keys: eid = unique ID, status = available|reclaimable
				// Value of $unclaimed is a unique id, though we won't particularly use it
				if (isset($unclaimed['status']) && 'reclaimable' == $unclaimed['status']) {
					$blurb ='<p><strong>'.esc_html__('Available to claim on this site', 'updraftplus').' - <a aria-label="'.
						/* translators: %s: Add-on name. */
						sprintf(esc_html__('%s available to claim on this site.', 'updraftplus').' '.
						esc_html__('Follow this link to activate this licence', 'updraftplus'), $name).'" class="updraftplus-unclaimed-addon" href="#" onclick="return udm_claim(\''.esc_js($key).'\');">'.esc_html__('reclaim it for use on this site', 'updraftplus').'</a></strong></p>';
				} else {
					$blurb ='<p><strong>'.esc_html__('You have an inactive purchase', 'updraftplus').' - <a class= "updraftplus-unclaimed-addon" href="#" onclick="return udm_claim(\''.esc_js($key).'\');">'.esc_html__('claim it for use on this site', 'updraftplus').'</a></strong></p>';
				}
				$preblurb = "<div style=\"border: 2px solid #189c5f;padding: 10px;width: 72px;height: 72px;border-radius: 5px;position: relative;background: #1a9c5e0f;\"><img style=\"-webkit-filter: grayscale(100%);filter: grayscale(100%);width: 56px;position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);\" src=\"$urlbase/$key.png\"></div>";
			} else {
				/* translators: %s: Add-on name */
				$blurb = '<p><a aria-label="'.sprintf(esc_html__('Get %s from teamupdraft.com', 'updraftplus'), $name).
						(($updraftplus_addons2->connected) ? '' : ' '.esc_html__('(or connect using the form on this page if you have already purchased it)', 'updraftplus')).'" target="_blank" href="'.$mother.$shopurl.'">'.esc_html__('Get it from teamupdraft.com', 'updraftplus').'</a>'.(($updraftplus_addons2->connected) ? '' : ' '.esc_html__('(or connect using the login form on this page if you have already bought it)', 'updraftplus')).'</p>';
						/* translators: %s: Add-on name. */
				$preblurb = "<div style=\"border: 2px solid #8c8c8c;padding: 10px;width: 72px;height: 72px;border-radius: 5px;position: relative;background: #8c8c8c0f;\"><a href=\"".$mother.$shopurl."\" title=\"".sprintf(esc_html__('Buy %s', 'updraftplus'), $name)."\"><img style=\"-webkit-filter: grayscale(100%);filter: grayscale(100%);width: 56px;position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);\" src=\"$urlbase/$key.png\" alt=\"".esc_html__('Buy It', 'updraftplus')."\"></a></div>";
			}
		}
		if ($extra_description) $extra_description = '<p>'.$extra_description.'</p>';
		return "<div id=\"addon-$key\" class=\"updraftplus-addon-box\" style=\"border: 1px solid #d0d0d0;border-radius: 5px;padding: 12px;max-width: 680px;margin-bottom: 22px;background-color:#fff;box-shadow: 0 0 20px 5px rgb(0 0 0 / 7%);display: flex;justify-content: space-between;align-items: center;\">
					<div style=\"max-width: 80%;\"><h2>$name</h2><p>$description</p>$extra_description$blurb</div>
					$preblurb
				</div>";
	}

	/**
	 * Adds links to the plugin on the 'Plugins' dashboard page, via hooking the appropriate filter.
	 *
	 * @param Array  $links - current array of links
	 * @param String $file  - the current file that links are being fetched for
	 */
	public function action_links($links, $file) {
		if ('updraftplus/updraftplus.php' == $file) {
			array_unshift($links, '<a href="'.$this->options->addons_admin_url().'">'.esc_html__('Manage Addons', 'updraftplus').'</a>');
		}
		return $links;
	}

	/**
	 * Print the HTML wrapper for the UpdraftPlus connection status.
	 *
	 * Output an empty container that can be populated with connection status content dynamically.
	 *
	 * @return void
	 */
	public function print_connection_status_html_wrapper() {
		echo '<div class="updraftplus-udaddons-connection-status-wrapper"></div>';
	}
}
