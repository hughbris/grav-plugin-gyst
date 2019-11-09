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
		/*
		dump($event['action']);
		$form_frontmatter = $this->grav['page']->header()->forms;
		dump($form_frontmatter[$form->name]['process']);
		$this->grav['page']->modifyHeader('forms.frew', ['foo'=>'bar']);

		dump($this->grav['page']->header($this->grav['page']->header())); $this->grav['page']->save(); exit;
		*/
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

		$provider_options = $this->getProviderOptions($params['provider']);
		// dump($provider_options); exit;

		$sheets = new GoogleSpreadsheetCollection($provider_options); // TODO: abstract for any supported provider
		// dump($sheets); exit;

		// dump($sheets->spreadsheets->create($spreadsheet)); exit;
		// try     public function modifyHeader($key, $value) # https://github.com/getgrav/grav/blob/8678f22f6bf94e0a5c862f864e5a3d06cc31dd07/system/src/Grav/Common/Page/Page.php#L487

		$ssid = $this->getSpreadsheetId($params, $sheets);

		// dump($sheets->spreadsheets->get($ssid)); exit;
		// dump($sheets->spreadsheets_values->get($ssid, 'Sheet1')); exit;

		if (array_key_exists('sheetname', $params)) {
			$sheetname = $twig->processString($params['sheetname'], $vars);
		}
		else {
			$sheetname = $form['name'];
		}

		// dump($sheets->hasSheetByTitle($sheetname));
		foreach($sheets->spreadsheets->get($ssid)->getSheets() as $s) {
			$sheet_titles[] = $s['properties']['title'];
		}
		$new_sheet = (array_search($sheetname, $sheet_titles) === FALSE);
		// dump($sheet_titles);

		$newSheetRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
			'requests' => [
				'addSheet' => [
					'properties' => [
						'title' => $sheetname,
						],
					],
				],
			]);

		if ($new_sheet) {
			$sheets->spreadsheets->batchUpdate($ssid, $newSheetRequest);
			printf("'%s' sheet added.<br/>", $sheetname);
		}

		// https://www.fillup.io/post/read-and-write-google-sheets-from-php/

		$form_values = array_values($form->value()->toArray()); // TODO: put check filters in here for usable field types
		// TODO: support 'fields' action parameter if provided

		$rowBody = new \Google_Service_Sheets_ValueRange([
			// 'range' => $updateRange,
			// 'majorDimension' => 'ROWS',
			'values' => [ $form_values ],
		]);

		$result = $sheets->spreadsheets_values->append(
			$ssid,
			$sheetname,
			$rowBody,
			[
				'valueInputOption' => 'RAW', // 'USER_ENTERED']
				'insertDataOption' => 'INSERT_ROWS',
			]

		);
		printf("%d rows appended.<br/>", $result->getUpdates()->getUpdatedRows());
		// dump($sheets->spreadsheets_values->get($ssid, $sheetname)['values']);
		// dump($sheets->spreadsheets->get($ssid)); exit;
	}

	private function getProviderOptions($identifier=NULL) {
		if (empty($identifier)) {
			return $this->settings['providers'];
		}
		else {
			foreach($this->settings['providers'] as $key=>$provider) {
				if ($key == $identifier) {
					return $provider;
				}
			}
		}
		// still here? handle not found ..
		return; # TODO
	}

	/* ****************
	Retrieve a spreadsheet(s?) ID, handling fallback if not specified in form action options.
	$collection_to_check: Optional GoogleSpreadsheetCollection object, in which spreadsheet id's existence is checked if provided (not currently working!)
	******************* */
	private function getSpreadsheetId($action_properties, $confirm_in_collection=NULL) {
		$provider_settings = $this->settings['providers'][$action_properties['provider']];

		if (array_key_exists('spreadsheet', $action_properties)) {
			$ssid = $action_properties['spreadsheet'];
		}
		elseif (array_key_exists('default_id', $provider_settings)) {
			$ssid = $provider_settings['default_id'];
		}

		else {
			return NULL; // FIXME: throw exception
		}

		return (empty($confirm_in_collection) OR $this->spreadsheetExists($ssid, $confirm_in_collection)) ? $ssid : NULL;
	}

	/* *****************************
	Dummy/stub function always returning TRUE until it works ...
	Eventually, test if spreadsheets exists with provided $id in $collection.
	******************************** */
	private function spreadsheetExists($id, $collection) {
		return TRUE; // FIXME ..

		// NB. does not work. It is most certainly a crap API we are dealing with, but also the exception eludes being caught or even muted(wtf??)
		try{
			/*
			// even via HTTP I can't catch the exception ...
			$client = new \Google_Client();
			$client->setAuthConfig('/PATH');
			$client->addScope(\Google_Service_Sheets::SPREADSHEETS);
			$httpClient = $client->authorize();
			$response = $httpClient->get("https://sheets.googleapis.com/v4/spreadsheets/{$ssid}");
			*/
			$response = @($confirm_in_collection->spreadsheets->get($id)) OR die('dang!');
			dump($response);
		}
		catch(\Exception $e) {
			dump('nooo');
			// return;
		}
		return TRUE;
	}
}

class GoogleSpreadsheetCollection extends \Google_Service_Sheets {

	function __construct($options) {
		$client = new \Google_Client();
		$client->setAuthConfig($options['auth']['path']);
		// $client->useApplicationDefaultCredentials();
		// $client->addScope(Google_Service_Sheets_Spreadsheet::DRIVE);
		$client->addScope(\Google_Service_Sheets::SPREADSHEETS);

		return parent::__construct($client); // FIXME: remove return
		// $sheets = new \Google_Service_Sheets($client); // dump($sheets); exit;
		/*
		$spreadsheet = new \Google_Service_Sheets_Spreadsheet([
			'properties' => [
				'title' => 'FOOTESTFIXME'
			]
		]);
		*/
	}

	/*
	// TODO: object requires more data intialised
	function hasSheetByTitle() {
		foreach($sheets->spreadsheets->get($ssid)->getSheets() as $s) {
			$sheet_titles[] = $s['properties']['title'];
		}

		dump($sheet_titles); exit;

	}
	*/

}
