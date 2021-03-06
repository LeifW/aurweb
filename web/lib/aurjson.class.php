<?php

include_once("aur.inc.php");

/*
 * This class defines a remote interface for fetching data from the AUR using
 * JSON formatted elements.
 *
 * @package rpc
 * @subpackage classes
 */
class AurJSON {
	private $dbh = false;
	private $version = 1;
	private static $exposed_methods = array(
		'search', 'info', 'multiinfo', 'msearch', 'suggest',
		'suggest-pkgbase'
	);
	private static $fields_v1 = array(
		'Packages.ID', 'Packages.Name',
		'PackageBases.ID AS PackageBaseID',
		'PackageBases.Name AS PackageBase', 'Version',
		'Description', 'URL', 'NumVotes', 'OutOfDateTS AS OutOfDate',
		'Users.UserName AS Maintainer',
		'SubmittedTS AS FirstSubmitted', 'ModifiedTS AS LastModified',
		'Licenses.Name AS License'
	);
	private static $fields_v2 = array(
		'Packages.ID', 'Packages.Name',
		'PackageBases.ID AS PackageBaseID',
		'PackageBases.Name AS PackageBase', 'Version',
		'Description', 'URL', 'NumVotes', 'OutOfDateTS AS OutOfDate',
		'Users.UserName AS Maintainer',
		'SubmittedTS AS FirstSubmitted', 'ModifiedTS AS LastModified'
	);
	private static $fields_v4 = array(
		'Packages.ID', 'Packages.Name',
		'PackageBases.ID AS PackageBaseID',
		'PackageBases.Name AS PackageBase', 'Version',
		'Description', 'URL', 'NumVotes', 'Popularity',
		'OutOfDateTS AS OutOfDate', 'Users.UserName AS Maintainer',
		'SubmittedTS AS FirstSubmitted', 'ModifiedTS AS LastModified'
	);
	private static $numeric_fields = array(
		'ID', 'PackageBaseID', 'NumVotes', 'OutOfDate',
		'FirstSubmitted', 'LastModified'
	);
	private static $decimal_fields = array(
		'Popularity'
	);

	/*
	 * Handles post data, and routes the request.
	 *
	 * @param string $post_data The post data to parse and handle.
	 *
	 * @return string The JSON formatted response data.
	 */
	public function handle($http_data) {
		/*
		 * Unset global aur.inc.php Pragma header. We want to allow
		 * caching of data in proxies, but require validation of data
		 * (if-none-match) if possible.
		 */
		header_remove('Pragma');
		/*
		 * Overwrite cache-control header set in aur.inc.php to allow
		 * caching, but require validation.
		 */
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Content-Type: application/json, charset=utf-8');

		if (isset($http_data['v'])) {
			$this->version = intval($http_data['v']);
		}
		if ($this->version < 1 || $this->version > 4) {
			return $this->json_error('Invalid version specified.');
		}

		if (!isset($http_data['type']) || !isset($http_data['arg'])) {
			return $this->json_error('No request type/data specified.');
		}
		if (!in_array($http_data['type'], self::$exposed_methods)) {
			return $this->json_error('Incorrect request type specified.');
		}

		$this->dbh = DB::connect();

		$type = str_replace('-', '_', $http_data['type']);
		$json = call_user_func(array(&$this, $type), $http_data['arg']);

		$etag = md5($json);
		header("Etag: \"$etag\"");
		/*
		 * Make sure to strip a few things off the
		 * if-none-match header. Stripping whitespace may not
		 * be required, but removing the quote on the incoming
		 * header is required to make the equality test.
		 */
		$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
			trim($_SERVER['HTTP_IF_NONE_MATCH'], "\t\n\r\" ") : false;
		if ($if_none_match && $if_none_match == $etag) {
			header('HTTP/1.1 304 Not Modified');
			return;
		}

		if (isset($http_data['callback'])) {
			header('content-type: text/javascript');
			return $http_data['callback'] . "({$json})";
		} else {
			header('content-type: application/json');
			return $json;
		}
	}

	/*
	 * Returns a JSON formatted error string.
	 *
	 * @param $msg The error string to return
	 *
	 * @return mixed A json formatted error response.
	 */
	private function json_error($msg) {
		header('content-type: application/json');
		if ($this->version < 3) {
			return $this->json_results('error', 0, $msg, NULL);
		} elseif ($this->version >= 3) {
			return $this->json_results('error', 0, array(), $msg);
		}
	}

	/*
	 * Returns a JSON formatted result data.
	 *
	 * @param $type The response method type.
	 * @param $data The result data to return
	 * @param $error An error message to include in the response
	 *
	 * @return mixed A json formatted result response.
	 */
	private function json_results($type, $count, $data, $error) {
		$json_array = array(
			'version' => $this->version,
			'type' => $type,
			'resultcount' => $count,
			'results' => $data
		);

		if ($error) {
			$json_array['error'] = $error;
		}

		return json_encode($json_array);
	}

	private function get_extended_fields($pkgid) {
		$query = "SELECT DependencyTypes.Name AS Type, " .
			"PackageDepends.DepName AS Name, " .
			"PackageDepends.DepCondition AS Cond " .
			"FROM PackageDepends " .
			"LEFT JOIN DependencyTypes " .
			"ON DependencyTypes.ID = PackageDepends.DepTypeID " .
			"WHERE PackageDepends.PackageID = " . $pkgid . " " .
			"UNION SELECT RelationTypes.Name AS Type, " .
			"PackageRelations.RelName AS Name, " .
			"PackageRelations.RelCondition AS Cond " .
			"FROM PackageRelations " .
			"LEFT JOIN RelationTypes " .
			"ON RelationTypes.ID = PackageRelations.RelTypeID " .
			"WHERE PackageRelations.PackageID = " . $pkgid . " " .
			"UNION SELECT 'groups' AS Type, Groups.Name, '' AS Cond " .
			"FROM Groups INNER JOIN PackageGroups " .
			"ON PackageGroups.PackageID = " . $pkgid . " " .
			"AND PackageGroups.GroupID = Groups.ID " .
			"UNION SELECT 'license' AS Type, Licenses.Name, '' AS Cond " .
			"FROM Licenses INNER JOIN PackageLicenses " .
			"ON PackageLicenses.PackageID = " . $pkgid . " " .
			"AND PackageLicenses.LicenseID = Licenses.ID";
		$result = $this->dbh->query($query);

		if (!$result) {
			return null;
		}

		$type_map = array(
			'depends' => 'Depends',
			'makedepends' => 'MakeDepends',
			'checkdepends' => 'CheckDepends',
			'optdepends' => 'OptDepends',
			'conflicts' => 'Conflicts',
			'provides' => 'Provides',
			'replaces' => 'Replaces',
			'groups' => 'Groups',
			'license' => 'License',
		);
		$data = array();
		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$type = $type_map[$row['Type']];
			$data[$type][] = $row['Name'] . $row['Cond'];
		}

		return $data;
	}

	private function process_query($type, $where_condition) {
		$max_results = config_get_int('options', 'max_rpc_results');
		$package_url = config_get('options', 'package_url');

		if ($this->version == 1) {
			$fields = implode(',', self::$fields_v1);
			$query = "SELECT {$fields} " .
				"FROM Packages LEFT JOIN PackageBases " .
				"ON PackageBases.ID = Packages.PackageBaseID " .
				"LEFT JOIN Users " .
				"ON PackageBases.MaintainerUID = Users.ID " .
				"LEFT JOIN PackageLicenses " .
				"ON PackageLicenses.PackageID = Packages.ID " .
				"LEFT JOIN Licenses " .
				"ON Licenses.ID = PackageLicenses.LicenseID " .
				"WHERE ${where_condition} " .
				"AND PackageBases.PackagerUID IS NOT NULL " .
				"GROUP BY Packages.ID " .
				"LIMIT $max_results";
		} elseif ($this->version >= 2) {
			if ($this->version == 2 || $this->version == 3) {
				$fields = implode(',', self::$fields_v2);
			} else if ($this->version == 4) {
				$fields = implode(',', self::$fields_v4);
			}
			$query = "SELECT {$fields} " .
				"FROM Packages LEFT JOIN PackageBases " .
				"ON PackageBases.ID = Packages.PackageBaseID " .
				"LEFT JOIN Users " .
				"ON PackageBases.MaintainerUID = Users.ID " .
				"WHERE ${where_condition} " .
				"AND PackageBases.PackagerUID IS NOT NULL " .
				"LIMIT $max_results";
		}
		$result = $this->dbh->query($query);

		if ($result) {
			$resultcount = 0;
			$search_data = array();
			while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
				$resultcount++;
				$row['URLPath'] = sprintf(config_get('options', 'snapshot_uri'), urlencode($row['PackageBase']));
				if ($this->version < 4) {
					$row['CategoryID'] = 1;
				}

				/*
				 * Unfortunately, mysql_fetch_assoc() returns
				 * all fields as strings. We need to coerce
				 * numeric values into integers to provide
				 * proper data types in the JSON response.
				 */
				foreach (self::$numeric_fields as $field) {
					$row[$field] = intval($row[$field]);
				}

				foreach (self::$decimal_fields as $field) {
					$row[$field] = floatval($row[$field]);
				}

				if ($this->version >= 2 && ($type == 'info' || $type == 'multiinfo')) {
					$row = array_merge($row, $this->get_extended_fields($row['ID']));
				}

				if ($this->version < 3) {
					if ($type == 'info') {
						$search_data = $row;
						break;
					} else {
						array_push($search_data, $row);
					}
				} elseif ($this->version >= 3) {
					array_push($search_data, $row);
				}
			}

			if ($resultcount === $max_results) {
				return $this->json_error('Too many package results.');
			}

			return $this->json_results($type, $resultcount, $search_data, NULL);
		} else {
			return $this->json_results($type, 0, array(), NULL);
		}
	}

	/*
	 * Parse the args to the multiinfo function. We may have a string or an
	 * array, so do the appropriate thing. Within the elements, both * package
	 * IDs and package names are valid; sort them into the relevant arrays and
	 * escape/quote the names.
	 *
	 * @param $args the arg string or array to parse.
	 *
	 * @return mixed An array containing 'ids' and 'names'.
	 */
	private function parse_multiinfo_args($args) {
		if (!is_array($args)) {
			$args = array($args);
		}

		$id_args = array();
		$name_args = array();
		foreach ($args as $arg) {
			if (!$arg) {
				continue;
			}
			if (is_numeric($arg)) {
				$id_args[] = intval($arg);
			} else {
				$name_args[] = $this->dbh->quote($arg);
			}
		}

		return array('ids' => $id_args, 'names' => $name_args);
	}

	/*
	 * Performs a fulltext mysql search of the package database.
	 *
	 * @param $keyword_string A string of keywords to search with.
	 *
	 * @return mixed Returns an array of package matches.
	 */
	private function search($keyword_string) {
		if (strlen($keyword_string) < 2) {
			return $this->json_error('Query arg too small');
		}

		$keyword_string = $this->dbh->quote("%" . addcslashes($keyword_string, '%_') . "%");

		$where_condition = "(Packages.Name LIKE $keyword_string OR ";
		$where_condition .= "Description LIKE $keyword_string)";

		return $this->process_query('search', $where_condition);
	}

	/*
	 * Returns the info on a specific package.
	 *
	 * @param $pqdata The ID or name of the package. Package Query Data.
	 *
	 * @return mixed Returns an array of value data containing the package data
	 */
	private function info($pqdata) {
		if (is_numeric($pqdata)) {
			$where_condition = "Packages.ID = $pqdata";
		} else {
			$where_condition = "Packages.Name = " . $this->dbh->quote($pqdata);
		}

		return $this->process_query('info', $where_condition);
	}

	/*
	 * Returns the info on multiple packages.
	 *
	 * @param $pqdata A comma-separated list of IDs or names of the packages.
	 *
	 * @return mixed Returns an array of results containing the package data
	 */
	private function multiinfo($pqdata) {
		$args = $this->parse_multiinfo_args($pqdata);
		$ids = $args['ids'];
		$names = $args['names'];

		if (!$ids && !$names) {
			return $this->json_error('Invalid query arguments');
		}

		$where_condition = "";
		if ($ids) {
			$ids_value = implode(',', $args['ids']);
			$where_condition .= "Packages.ID IN ($ids_value) ";
		}
		if ($ids && $names) {
			$where_condition .= "OR ";
		}
		if ($names) {
			/*
			 * Individual names were quoted in
			 * parse_multiinfo_args().
			 */
			$names_value = implode(',', $args['names']);
			$where_condition .= "Packages.Name IN ($names_value) ";
		}

		return $this->process_query('multiinfo', $where_condition);
	}

	/*
	 * Returns all the packages for a specific maintainer.
	 *
	 * @param $maintainer The name of the maintainer.
	 *
	 * @return mixed Returns an array of value data containing the package data
	 */
	private function msearch($maintainer) {
		$maintainer = $this->dbh->quote($maintainer);

		$where_condition = "Users.Username = $maintainer ";

		return $this->process_query('msearch', $where_condition);
	}

	/*
	 * Get all package names that start with $search.
	 *
	 * @param string $search Search string.
	 *
	 * @return string The JSON formatted response data.
	 */
	private function suggest($search) {
		$query = "SELECT Packages.Name FROM Packages ";
		$query.= "LEFT JOIN PackageBases ";
		$query.= "ON PackageBases.ID = Packages.PackageBaseID ";
		$query.= "WHERE Packages.Name LIKE ";
		$query.= $this->dbh->quote(addcslashes($search, '%_') . '%');
		$query.= " AND PackageBases.PackagerUID IS NOT NULL ";
		$query.= "ORDER BY Name ASC LIMIT 20";

		$result = $this->dbh->query($query);
		$result_array = array();

		if ($result) {
			$result_array = $result->fetchAll(PDO::FETCH_COLUMN, 0);
		}

		return json_encode($result_array);
	}

	/*
	 * Get all package base names that start with $search.
	 *
	 * @param string $search Search string.
	 *
	 * @return string The JSON formatted response data.
	 */
	private function suggest_pkgbase($search) {
		$query = "SELECT Name FROM PackageBases WHERE Name LIKE ";
		$query.= $this->dbh->quote(addcslashes($search, '%_') . '%');
		$query.= " AND PackageBases.PackagerUID IS NOT NULL ";
		$query.= "ORDER BY Name ASC LIMIT 20";

		$result = $this->dbh->query($query);
		$result_array = array();

		if ($result) {
			$result_array = $result->fetchAll(PDO::FETCH_COLUMN, 0);
		}

		return json_encode($result_array);
	}
}

