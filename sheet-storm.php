<?php
namespace Grav\Plugin;

require_once __DIR__ . '/vendor/autoload.php';

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class SheetStormPlugin
 * @package Grav\Plugin
 */
class SheetStormPlugin extends Plugin
{
    protected $settings;

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        $this->settings = $this->grav['config']->get('plugins.sheet-storm');

        // Enable the main event we are interested in
        $this->enable([
            'onFormProcessed' => ['customFormActions', 0],
        ]);
    }

/**
	 * [customFormActions] Process custom form actions defined in this plugin:
	 *
	 * - stash_pdf: stash a PDF in the cloud
	 *
	 * @param Event $event
	 * @throws \RuntimeException
	 */
	public function customFormActions(Event $event)	{
		$form = $event['form'];
		$action = $event['action'];

		// stash PDF custom action
		switch ($action) {
			case 'sheet_row':
				$this->saveToRow($event);
				break;
		}
	}

	/**
	 * Save PDF formatted data into a cloud stash
	 *
	 * @param Event $event
	 */
	public function saveToRow(Event $event) {

		$form = $event['form'];
		$params = $event['params'];

		$format = array_key_exists('dateformat', $params) ? $params['dateformat'] : 'Ymd-His-u';

		if (array_key_exists('dateraw', $params) AND (bool) $params['dateraw']) {
			$datestamp = date($format);
		}
		else {
			$utimestamp = microtime(true);
			$timestamp = floor($utimestamp);
			$milliseconds = round(($utimestamp - $timestamp) * 1000000);
			$datestamp = date(preg_replace('`(?<!\\\\)u`', \sprintf('%06d', $milliseconds), $format), $timestamp);
		}

		$twig = $this->grav['twig'];
		$vars = [
			'form' => $form,
		];
		$twig->itemData = $form->getData(); // FIXME for default data.html template below - might work OK

		$provider_options = $this->getProvider('google_sheets');

		$client = new \Google_Client();
		$client->setAuthConfig($provider_options['path']);
		// $client->useApplicationDefaultCredentials();
		// $client->addScope(Google_Service_Sheets_Spreadsheet::DRIVE);
		$client->addScope(\Google_Service_Sheets::SPREADSHEETS);

		$sheets = new \Google_Service_Sheets($client); // dump($sheets); exit;
		$spreadsheet = new \Google_Service_Sheets_Spreadsheet([
			'properties' => [
				'title' => 'FOOTESTFIXME'
			]
		]);
		// $sheets->spreadsheets->create($spreadsheet);

		$ssid = $params['sheet'];
		// dump($sheets->spreadsheets->get($ssid)); exit;
		// dump($sheets->spreadsheets_values->get($ssid, 'Sheet1')); exit;

		// https://www.fillup.io/post/read-and-write-google-sheets-from-php/

		$form_values = array_values($form->value()->toArray()); // TODO: put check filters in here for usable field types

		$rowBody = new \Google_Service_Sheets_ValueRange([
			// 'range' => $updateRange,
			// 'majorDimension' => 'ROWS',
			'values' => [ $form_values ],
		]);

		$result = $sheets->spreadsheets_values->append(
			$ssid,
			'Sheet1',
			$rowBody,
			[
				'valueInputOption' => 'RAW', // 'USER_ENTERED']
				'insertDataOption' => 'INSERT_ROWS',
			]

		);
		printf("%d rows appended.", $result->getUpdates()->getUpdatedRows());
		dump($sheets->spreadsheets->get($ssid)); exit;
	}

	private function getProvider($vendor=NULL) {
		if (empty($vendor)) {
			return $this->settings['authentication']['providers'];
		}
		else {
			foreach($this->settings['authentication']['providers'] as $provider) {
				if ($provider['name'] == $vendor) {
					return $provider;
				}
			}
		}
		// still here? handle not found ..
		return; # TODO
	}

}
