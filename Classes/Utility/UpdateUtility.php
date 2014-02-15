<?php
namespace Evoweb\StoreFinder\Utility;

/**
 * Class UpdateUtility
 *
 * @package Evoweb\StoreFinder\Utility
 */
class UpdateUtility {
	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection $database
	 */
	protected $database;

	/**
	 * @var array
	 */
	protected $mapping = array(
		'attributes' => array(
			'uid' => 'import_id',
			'pid' => 'pid',
			'tstamp' => 'tstamp',
			'crdate' => 'crdate',
			'cruser_id' => 'cruser_id',
			'sorting' => 'sorting',
			'hidden' => 'hidden',
			'deleted' => 'deleted',
			'sys_language_uid' => 'sys_language_uid',
			'l10n_parent' => 'value:attributes:l10n_parent',
			'l10n_diffsource' => 'l10n_diffsource',
			'icon' => 'icon',
			'name' => 'name',
		),
		'categories' => array(
			'uid' => 'import_id',
			'pid' => 'pid',
			'parentuid' => 'value:categories:parent',
			'tstamp' => 'tstamp',
			'crdate' => 'crdate',
			'cruser_id' => 'cruser_id',
			'sorting' => 'sorting',
			'hidden' => 'hidden',
			'deleted' => 'deleted',
			'sys_language_uid' => 'sys_language_uid',
			'l10n_parent' => 'value:categories:l10n_parent',
			'l10n_diffsource' => 'l10n_diffsource',
			'fe_group' => '',
			'name' => 'title',
			'description' => 'description',
		),
		'locations' => array(
			'uid' => 'import_id',
			'pid' => 'pid',
			'tstamp' => 'tstamp',
			'crdate' => 'crdate',
			'cruser_id' => 'cruser_id',
			'sorting' => 'sorting',
			'deleted' => 'deleted',
			'hidden' => 'hidden',
			'endtime' => 'endtime',
			'fe_group' => 'fe_group',
			'storename' => 'name',
			'storeid' => 'storeid',
			'attributes' => 'comma:mm:attributes:tx_storefinder_location_attribute_mm:uid_local:tx_storefinder_domain_model_attribute:attributes',
			'address' => 'address',
			'additionaladdress' => 'additionaladdress',
			'city' => 'city',
			'contactperson' => 'person',
			'state' => 'state',
			'zipcode' => 'zipcode',
				// @todo implement 1:1 references for country
			'country' => 'ref:country',
			'products' => 'products',
			'email' => 'email',
			'phone' => 'phone',
			'mobile' => 'mobile',
			'fax' => 'fax',
			'hours' => 'hours',
			'url' => 'url',
			'notes' => 'notes',
			'media' => 'media',
				// @todo implement fal references for images
			'imageurl' => 'ref:image',
			'icon' => 'icon',
			'content' => 'content',
			'use_coordinate' => '',
			'categoryuid' => 'comma:mm:categories:sys_category_record_mm:uid_foreign:tx_storefinder_domain_model_location:categories',
			'lat' => 'latitude',
			'lon' => 'longitude',
			'geocode' => '',
			'relatedto' => 'finish_comma:mm:locations:tx_storefinder_location_location_mm:uid_local:tx_storefinder_domain_model_location:related',
		),
	);

	/**
	 * @var array
	 */
	protected $records = array(
		'attributes' => array(),
		'categories' => array(),
		'locations' => array(),
	);

	/**
	 * @var array
	 */
	protected $messageArray = array();


	/**
	 * Performes the Updates
	 * Outputs HTML Content
	 *
	 * @return string
	 */
	public function main() {
		$this->database = $GLOBALS['TYPO3_DB'];

		$content = '';

		if ($this->access()) {
			if ($this->warningAccepted()) {
				$this->migrateAttributes();
				$this->migrateCategories();
				$this->migrateLocations();
			} else {
				$content = $this->renderWarning();
			}
		}

		return $content ?: $this->generateOutput();
	}


	/**
	 * Render warning
	 *
	 * @return string
	 */
	protected function renderWarning() {
		$action = \TYPO3\CMS\Core\Utility\GeneralUtility::linkThisScript(array(
			'M' => \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('M'),
			'tx_extensionmanager_tools_extensionmanagerextensionmanager' =>
				\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('tx_extensionmanager_tools_extensionmanagerextensionmanager')
		));

		$content = '</br>Do you want to start the migration?</br>
			<form action="' . $action . '" method="POST">
				<button name="tx_storefinder_update[confirm]" value="1">Start migration</button>
			</form>';

		return $content;
	}

	/**
	 * Check if warning was confirmed
	 *
	 * @return bool
	 */
	protected function warningAccepted() {
		$updateVars = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('tx_storefinder_update');

		return (bool)$updateVars['confirm'];
	}

	/**
	 * Generates output by using flash messages
	 *
	 * @return string
	 */
	protected function generateOutput() {
		$output = '';

		foreach ($this->messageArray as $messageItem) {
			/** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
			$flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				htmlspecialchars($messageItem['message']),
				'',
				\TYPO3\CMS\Core\Messaging\FlashMessage::INFO
			);

			$output .= $flashMessage->render();
		}

		return $output;
	}


	/**
	 * Migrate attributes
	 *
	 * @return void
	 */
	protected function migrateAttributes() {
		$attributes = $this->fetchAttributes();

		while (($row = $this->database->sql_fetch_assoc($attributes))) {
			$attribute = $this->mapFieldsPreImport($row, 'attributes');

			$table = 'tx_storefinder_domain_model_attribute';
			if (($record = $this->isAlreadyImported($attribute, $table))) {
				unset($attribute['import_id']);
				$this->database->exec_UPDATEquery($table, 'uid = ' . $record['uid'], $attribute);
				$this->records['attributes'][$row['uid']] = $attribute['uid'] = $record['uid'];
			} else {
				$this->database->exec_INSERTquery($table, $attribute);
				$this->records['attributes'][$row['uid']] = $attribute['uid'] = $this->database->sql_insert_id();
			}
		}

		$this->messageArray[] = array('message' => count($this->records['attributes']) . ' attributes migrated');
	}

	/**
	 * Migrate categories
	 *
	 * @return void
	 */
	protected function migrateCategories() {
		$categories = $this->fetchCategories();

		while (($row = $this->database->sql_fetch_assoc($categories))) {
			$category = $this->mapFieldsPreImport($row, 'categories');

			$table = 'sys_category';
			if (($record = $this->isAlreadyImported($category, $table))) {
				unset($category['import_id']);
				$this->database->exec_UPDATEquery($table, 'uid = ' . $record['uid'], $category);
				$this->records['categories'][$row['uid']] = $category['uid'] = $record['uid'];
			} else {
				$this->database->exec_INSERTquery($table, $category);
				$this->records['categories'][$row['uid']] = $category['uid'] = $this->database->sql_insert_id();
			}
		}

		$this->messageArray[] = array('message' => count($this->records['categories']) . ' categories migrated');
	}

	/**
	 * Migrate locations with relations
	 *
	 * @return void
	 */
	protected function migrateLocations() {
		$locations = $this->fetchLocations();

		while (($row = $this->database->sql_fetch_assoc($locations))) {
			$location = $this->mapFieldsPreImport($row, 'locations');

			$table = 'tx_storefinder_domain_model_location';
			if (($record = $this->isAlreadyImported($location, $table))) {
				unset($location['import_id']);
				$this->database->exec_UPDATEquery($table, 'uid = ' . $record['uid'], $location);
				$this->records['locations'][$row['uid']] = $location['uid'] = $record['uid'];
			} else {
				$this->database->exec_INSERTquery($table, $location);
				$this->records['locations'][$row['uid']] = $location['uid'] = $this->database->sql_insert_id();
			}

			$this->mapFieldsPostImport($row, $location, 'locations');
		}

		$this->database->sql_query('
			update tx_storefinder_domain_model_location AS l
				LEFT JOIN (
					SELECT uid_foreign, COUNT(*) AS count
					FROM sys_category_record_mm
					WHERE tablenames = \'tx_storefinder_domain_model_location\' AND fieldname = \'categories\'
					GROUP BY uid_foreign
				) AS c ON l.uid = c.uid_foreign
			set l.categories = COALESCE(c.count, 0);
		');
		$this->database->sql_query('
			update tx_storefinder_domain_model_location AS l
				LEFT JOIN (
					SELECT uid_local, COUNT(*) AS count
					FROM tx_storefinder_location_attribute_mm
					GROUP BY uid_local
				) AS a ON l.uid = a.uid_local
			set l.attributes = COALESCE(a.count, 0);
		');
		$this->database->sql_query('
			update tx_storefinder_domain_model_location AS l
				LEFT JOIN (
					SELECT uid_local, COUNT(*) AS count
					FROM tx_storefinder_location_location_mm
					GROUP BY uid_local
				) AS a ON l.uid = a.uid_local
			set l.related = COALESCE(a.count, 0);
		');

		$this->messageArray[] = array('message' => count($this->records['locations']) . ' locations migrated');
	}


	/**
	 * Fetch locator attributes
	 *
	 * @return \mysqli_result
	 */
	protected function fetchAttributes() {
		return $this->database->exec_SELECTquery(
			'*',
			'tx_locator_attributes',
			'deleted = 0',
			'',
			'sys_language_uid'
		);
	}

	/**
	 * Fetch locator categories
	 *
	 * @return \mysqli_result
	 */
	protected function fetchCategories() {
		return $this->database->exec_SELECTquery(
			'*',
			'tx_locator_categories',
			'deleted = 0',
			'',
			'sys_language_uid, parentuid'
		);
	}

	/**
	 * Fetch locator locations
	 *
	 * @return \mysqli_result
	 */
	protected function fetchLocations() {
		return $this->database->exec_SELECTquery(
			'*',
			'tx_locator_locations',
			'deleted = 0',
			'',
			'uid'
		);
	}


	/**
	 * Map fields pre import
	 *
	 * @param array $row
	 * @param string $table
	 * @return array
	 */
	protected function mapFieldsPreImport($row, $table) {
		$result = array();

		foreach ($this->mapping[$table] as $fieldFrom => $fieldTo) {
			if ($fieldTo && strpos($fieldTo, ':') === FALSE) {
				$result[$fieldTo] = is_null($row[$fieldFrom]) ? (string)$row[$fieldFrom] : $row[$fieldFrom];
			} elseif ($fieldTo) {
				$parts = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(':', $fieldTo);

				switch ($parts[0]) {
					case 'value':
						$result[$parts[2]] = (string) $this->records[$parts[1]][$row[$fieldFrom]];
						break;

					default:
				}
			}
		}

		return $result;
	}

	/**
	 * Map fields post import
	 *
	 * @param array $source
	 * @param array $destination
	 * @param string $table
	 * @return void
	 */
	protected function mapFieldsPostImport($source, $destination, $table) {
		foreach ($this->mapping[$table] as $fieldFrom => $fieldTo) {
			if (strpos($fieldTo, ':') !== FALSE) {
				$parts = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(':', $fieldTo);
				switch ($parts[0]) {
					case 'comma':
						if ($parts[1] == 'mm') {
							list(,, $sourceModel, $mmTable, $mmField, $destinationTable, $destinationField) = $parts;
							$sorting = 0;

							foreach (\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $source[$fieldFrom]) as $fromValue) {
								if ($mmField == 'uid_local') {
									$uidForeign = $this->records[$sourceModel][$fromValue];
									$uidLocal = $destination['uid'];
								} else {
									$uidLocal = $this->records[$sourceModel][$fromValue];
									$uidForeign = $destination['uid'];
								}

								if (!$uidLocal || !$uidForeign) {
									continue;
								}

								if (!$this->mmRelationExists($mmTable, $uidLocal, $uidForeign, $destinationTable)) {
									$this->database->exec_INSERTquery(
										$mmTable,
										array(
											'uid_local' => $uidLocal,
											'uid_foreign' => $uidForeign,
											'tablenames' => $destinationTable,
											'sorting' . ($mmField == 'uid_foreign' ? '_foreign' : '') => $sorting,
											'fieldname' => $destinationField,
										)
									);
								}

								$sorting++;
							}
						}
						break;

					default:
				}
			}
		}
	}

	/**
	 * Map fields after all records are imported
	 *
	 * @param array $source
	 * @param array $destination
	 * @param string $table
	 * @return void
	 */
	protected function mapFieldsFinish($source, $destination, $table) {
		foreach ($this->mapping[$table] as $fieldFrom => $fieldTo) {
			if (strpos($fieldTo, ':') !== FALSE) {
				$parts = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(':', $fieldTo);
				switch (str_replace('finish_', '', $parts[0])) {
					case 'comma':
						if ($parts[1] == 'mm') {
							list(,, $sourceModel, $mmTable, $mmField, $destinationTable, $destinationField) = $parts;
							$sorting = 0;
							foreach (\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $source[$fieldFrom]) as $fromValue) {
								if ($mmField == 'uid_local') {
									$uidForeign = $this->records[$sourceModel][$fromValue];
									$uidLocal = $destination['uid'];
								} else {
									$uidLocal = $this->records[$sourceModel][$fromValue];
									$uidForeign = $destination['uid'];
								}

								if (!$uidLocal || !$uidForeign) {
									continue;
								}

								if (!$this->mmRelationExists($mmTable, $uidLocal, $uidForeign, $destinationTable)) {
									$this->database->exec_INSERTquery(
										$mmTable,
										array(
											'uid_local' => $uidLocal,
											'uid_foreign' => $uidForeign,
											'tablenames' => $destinationTable,
											'sorting' . ($mmField == 'uid_foreign' ? '_foreign' : '') => $sorting,
											'fieldname' => $destinationField,
										)
									);
								}

								$sorting++;
							}
						}
						break;

					default:
				}
			}
		}
	}

	/**
	 * Checks if a mm relation exists
	 *
	 * @param string $mmTable
	 * @param int $uidLocal
	 * @param int $uidForeign
	 * @param string $tablenames
	 * @return bool
	 */
	protected function mmRelationExists($mmTable, $uidLocal, $uidForeign, $tablenames) {
		return (bool) $this->database->exec_SELECTcountRows(
			'*',
			$mmTable,
			'uid_local = ' . $uidLocal . ' AND uid_foreign = ' . $uidForeign . ' AND tablenames = \'' . $tablenames . '\''
		);
	}

	/**
	 * Check if a record is already imported
	 *
	 * @param array $record
	 * @param string $table
	 * @return bool
	 */
	protected function isAlreadyImported($record, $table) {
		return $this->database->exec_SELECTgetSingleRow(
			'uid',
			$table,
			'import_id = ' . $record['import_id'] . ' AND deleted = 0'
		);
	}

	/**
	 * Count locations
	 *
	 * @return int
	 */
	protected function countStoreFinderLocations() {
		return $this->database->exec_SELECTcountRows(
			'uid',
			'tx_storefinder_domain_model_location',
			'1' . \TYPO3\CMS\Backend\Utility\BackendUtility::BEenableFields('tx_storefinder_domain_model_location')
		);
	}

	/**
	 * echeck if the Ipdate is neassessary
	 *
	 * @return bool True if update should be perfomed
	 */
	public function access() {
		/** @var \TYPO3\CMS\Core\Database\DatabaseConnection $database */
		$database = $GLOBALS['TYPO3_DB'];

		$countLocations = $database->exec_SELECTcountRows(
			'l.uid',
			'tx_locator_locations AS l LEFT JOIN tx_storefinder_domain_model_location AS sl ON l.uid = sl.import_id',
			'l.deleted = 0'
		);
		$countAttributes = $database->exec_SELECTcountRows(
			'a.uid',
			'tx_locator_attributes AS a LEFT JOIN tx_storefinder_domain_model_attribute AS sa ON a.uid = sa.import_id',
			'a.deleted = 0'
		);

		$result = FALSE;
		if ($countLocations || $countAttributes) {
			$result = TRUE;
		}

		return $result;
	}
}