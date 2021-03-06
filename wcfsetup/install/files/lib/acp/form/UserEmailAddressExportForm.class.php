<?php
namespace wcf\acp\form;
use wcf\data\user\User;
use wcf\system\clipboard\ClipboardHandler;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\SystemException;
use wcf\system\WCF;
use wcf\util\ArrayUtil;
use wcf\util\StringUtil;

/**
 * Shows the export user mail addresses form.
 *
 * @author	Marcel Werk
 * @copyright	2001-2011 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	acp.form
 * @category 	Community Framework
 */
class UserEmailAddressExportForm extends ACPForm {
	public $activeMenuItem = 'wcf.acp.menu.link.user.management';
	public $neededPermissions = array('admin.user.canMailUser');
	
	public $fileType = 'csv';
	public $userIDs = array();
	public $separator = ',';
	public $textSeparator = '"'; 
	public $users = array();
	
	/**
	 * clipboard item type id
	 * @var	integer
	 */
	protected $typeID = null;
	
	/**
	 * @see	wcf\page\IPage::readParameters()
	 */
	public function readParameters() {
		parent::readParameters();
		
		// get type id
		$this->typeID = ClipboardHandler::getInstance()->getObjectTypeID('com.woltlab.wcf.user');
		if ($this->typeID === null) {
			throw new SystemException("clipboard item type 'com.woltlab.wcf.user' is unknown.");
		}
		
		// get user ids
		$users = ClipboardHandler::getInstance()->getMarkedItems($this->typeID);
		if (!isset($users['com.woltlab.wcf.user']) || empty($users['com.woltlab.wcf.user'])) throw new IllegalLinkException();
		
		// load users
		$this->userIDs = array_keys($users['com.woltlab.wcf.user']);
		$this->users = $users['com.woltlab.wcf.user'];
	}
	
	/**
	 * @see wcf\form\IForm::readFormParameters()
	 */
	public function readFormParameters() {
		parent::readFormParameters();
		
		if (isset($_POST['fileType']) && $_POST['fileType'] == 'xml') $this->fileType = $_POST['fileType'];
		if (isset($_POST['separator'])) $this->separator = $_POST['separator'];
		if (isset($_POST['textSeparator'])) $this->textSeparator = $_POST['textSeparator'];
	}
	
	/**
	 * @see wcf\form\IForm::save()
	 */
	public function save() {
		parent::save();
		
		// send content type
		header('Content-Type: text/'.$this->fileType.'; charset=UTF-8');
		header('Content-Disposition: attachment; filename="export.'.$this->fileType.'"');
		
		if ($this->fileType == 'xml') {
			echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<addresses>\n";
		}
		
		$conditions = new PreparedStatementConditionBuilder();
		$conditions->add("userID IN (?)", array($this->userIDs));
		
		// count users
		$sql = "SELECT	COUNT(*) AS count
			FROM	wcf".WCF_N."_user
			".$conditions;
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($conditions->getParameters());
		$count = $statement->fetchArray();
		
		// get users
		$sql = "SELECT		email
			FROM		wcf".WCF_N."_user
			".$conditions."
			ORDER BY	email";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($conditions->getParameters());
		
		$i = 0;
		while ($row = $statement->fetchArray()) {
			if ($this->fileType == 'xml') echo "<address><![CDATA[".StringUtil::escapeCDATA($row['email'])."]]></address>\n";
			else echo $this->textSeparator . $row['email'] . $this->textSeparator . ($i < $count['count'] ? $this->separator : '');
			$i++;
		}
		
		if ($this->fileType == 'xml') {
			echo "</addresses>";
		}
		
		$this->saved();
		
		// remove items
		ClipboardHandler::getInstance()->removeItems($this->typeID);
		
		exit;
	}
	
	/**
	 * @see wcf\page\IPage::assignVariables()
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		WCF::getTPL()->assign(array(
			'users' => $this->users,
			'separator' => $this->separator,
			'textSeparator' => $this->textSeparator,
			'fileType' => $this->fileType
		));
	}
}
