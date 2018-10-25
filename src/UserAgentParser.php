<?php

use donatj\UserAgentParser\Browser;
use donatj\UserAgentParser\Platform;

const UAP_KEY_PLATFORM = 'platform';
const UAP_KEY_BROWSER  = 'browser';
const UAP_KEY_VERSION  = 'version';

/**
 * Parses a user agent string into its important parts
 *
 * @author Jesse G. Donat <donatj@gmail.com>
 * @license MIT
 * @link https://github.com/donatj/PhpUserAgent
 * @link http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
 * @param string|null $u_agent User agent string to parse or null. Uses $_SERVER['HTTP_USER_AGENT'] on NULL
 * @throws \InvalidArgumentException on not having a proper user agent to parse.
 * @return string[] an array with browser, version and platform keys
 */
function parse_user_agent( $u_agent = null ) {
	if( $u_agent === null ) {
		if( isset($_SERVER['HTTP_USER_AGENT']) ) {
			$u_agent = $_SERVER['HTTP_USER_AGENT'];
		} else {
			throw new \InvalidArgumentException('parse_user_agent requires a user agent');
		}
	}

	$platform = null;
	$browser  = null;
	$version  = null;

	$empty = array( UAP_KEY_PLATFORM => $platform, UAP_KEY_BROWSER => $browser, UAP_KEY_VERSION => $version );

	if( !$u_agent ) {
		return $empty;
	}

	if( preg_match('/\((.*?)\)/m', $u_agent, $parent_matches) ) {
		preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|iPod|Linux|(Open|Net|Free)BSD|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|X11|(New\ )?Nintendo\ (WiiU?|3?DS|Switch)|Xbox(\ One)?)
				(?:\ [^;]*)?
				(?:;|$)/imx', $parent_matches[1], $result);

		$priority = array( 'Xbox One', 'Xbox', 'Windows Phone', 'Tizen', 'Android', 'FreeBSD', 'NetBSD', 'OpenBSD', 'CrOS', 'X11' );

		$result[UAP_KEY_PLATFORM] = array_unique($result[UAP_KEY_PLATFORM]);
		if( count($result[UAP_KEY_PLATFORM]) > 1 ) {
			if( $keys = array_intersect($priority, $result[UAP_KEY_PLATFORM]) ) {
				$platform = reset($keys);
			} else {
				$platform = $result[UAP_KEY_PLATFORM][0];
			}
		} elseif( isset($result[UAP_KEY_PLATFORM][0]) ) {
			$platform = $result[UAP_KEY_PLATFORM][0];
		}
	}

	if( $platform == 'linux-gnu' || $platform == 'X11' ) {
		$platform = Platform\LINUX;
	} elseif( $platform == 'CrOS' ) {
		$platform = Platform\CHROME_OS;
	}

	preg_match_all('%(?P<browser>Camino|Kindle(\ Fire)?|Firefox|Iceweasel|IceCat|Safari|MSIE|Trident|AppleWebKit|
				TizenBrowser|(?:Headless)?Chrome|YaBrowser|Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|CriOS|UCBrowser|Puffin|OculusBrowser|SamsungBrowser|
				Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
				Valve\ Steam\ Tenfoot|
				NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
				(?:\)?;?)
				(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
		$u_agent, $result);

	// If nothing matched, return null (to avoid undefined index errors)
	if( !isset($result[UAP_KEY_BROWSER][0]) || !isset($result[UAP_KEY_VERSION][0]) ) {
		if( preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?%ix', $u_agent, $result) ) {
			return array( UAP_KEY_PLATFORM => $platform ?: null, UAP_KEY_BROWSER => $result[UAP_KEY_BROWSER], UAP_KEY_VERSION => isset($result[UAP_KEY_VERSION]) ? $result[UAP_KEY_VERSION] ?: null : null );
		}

		return $empty;
	}

	if( preg_match('/rv:(?P<version>[0-9A-Z.]+)/i', $u_agent, $rv_result) ) {
		$rv_result = $rv_result[UAP_KEY_VERSION];
	}

	$browser = $result[UAP_KEY_BROWSER][0];
	$version = $result[UAP_KEY_VERSION][0];

	$lowerBrowser = array_map('strtolower', $result[UAP_KEY_BROWSER]);

	$find = function ( $search, &$key, &$value = null ) use ( $lowerBrowser ) {
		$search = (array)$search;

		foreach( $search as $val ) {
			$xkey = array_search(strtolower($val), $lowerBrowser);
			if( $xkey !== false ) {
				$value = $val;
				$key   = $xkey;

				return true;
			}
		}

		return false;
	};

	$key = 0;
	$val = '';
	if( $browser == 'Iceweasel' || strtolower($browser) == 'icecat' ) {
		$browser = Browser\FIREFOX;
	} elseif( $find('Playstation Vita', $key) ) {
		$platform = Platform\PLAYSTATION_VITA;
		$browser  = Browser\BROWSER;
	} elseif( $find(array( 'Kindle Fire', 'Silk' ), $key, $val) ) {
		$browser  = $val == 'Silk' ? 'Silk' : 'Kindle';
		$platform = Platform\KINDLE_FIRE;
		if( !($version = $result[UAP_KEY_VERSION][$key]) || !is_numeric($version[0]) ) {
			$version = $result[UAP_KEY_VERSION][array_search('Version', $result[UAP_KEY_BROWSER])];
		}
	} elseif( $find('NintendoBrowser', $key) || $platform == 'Nintendo 3DS' ) {
		$browser = Browser\NINTENDOBROWSER;
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('Kindle', $key, $platform) ) {
		$browser = $result[UAP_KEY_BROWSER][$key];
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('OPR', $key) ) {
		$browser = Browser\OPERA_NEXT;
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('Opera', $key, $browser) ) {
		$find('Version', $key);
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('Puffin', $key, $browser) ) {
		$version = $result[UAP_KEY_VERSION][$key];
		if( strlen($version) > 3 ) {
			$part = substr($version, -2);
			if( ctype_upper($part) ) {
				$version = substr($version, 0, -2);

				$flags = array( 'IP' => Platform\IPHONE, 'IT' => Platform\IPAD, 'AP' => Platform\ANDROID,
								'AT' => Platform\ANDROID, 'WP' => Platform\WINDOWS_PHONE, 'WT' => Platform\WINDOWS );
				if( isset($flags[$part]) ) {
					$platform = $flags[$part];
				}
			}
		}
	} elseif( $find('YaBrowser', $key, $browser) ) {
		$browser = Browser\YANDEX;
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find(array( Browser\IEMOBILE, Browser\EDGE, Browser\MIDORI, Browser\VIVALDI, Browser\OCULUSBROWSER, Browser\SAMSUNGBROWSER, Browser\VALVE_STEAM_TENFOOT, Browser\CHROME, Browser\HEADLESSCHROME ), $key, $browser) ) {
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $rv_result && $find('Trident', $key) ) {
		$browser = Browser\MSIE;
		$version = $rv_result;
	} elseif( $find('UCBrowser', $key) ) {
		$browser = Browser\UC_BROWSER;
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $find('CriOS', $key) ) {
		$browser = Browser\CHROME;
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $browser == 'AppleWebKit' ) {
		if( $platform == 'Android' ) {
			$browser = Browser\ANDROID_BROWSER;
		} elseif( strpos($platform, 'BB') === 0 ) {
			$browser  = Browser\BLACKBERRY_BROWSER;
			$platform = Platform\BLACKBERRY;
		} elseif( $platform == 'BlackBerry' || $platform == 'PlayBook' ) {
			$browser  = Browser\BLACKBERRY_BROWSER;
		} else {
			$find('Safari', $key, $browser) || $find('TizenBrowser', $key, $browser);
		}

		$find('Version', $key);
		$version = $result[UAP_KEY_VERSION][$key];
	} elseif( $pKey = preg_grep('/playstation \d/i', array_map('strtolower', $result[UAP_KEY_BROWSER])) ) {
		$pKey = reset($pKey);

		$platform = 'PlayStation ' . preg_replace('/\D/', '', $pKey);
		$browser  = Browser\NETFRONT;
	}

	return array( UAP_KEY_PLATFORM => $platform ?: null, UAP_KEY_BROWSER => $browser ?: null, UAP_KEY_VERSION => $version ?: null );
}
