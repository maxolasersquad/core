<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\Connector\Sabre;

/**
 * ownCloud
 *
 * @author Vincent Petry
 * @copyright 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use \Sabre\DAV\PropFind;
use \Sabre\DAV\PropPatch;
use OCP\IUserSession;
use OCP\Share\IShare;

class SharesPlugin extends \Sabre\DAV\ServerPlugin
{

	// namespace
	const NS_OWNCLOUD = 'http://owncloud.org/ns';
	const SHARES_PROPERTYNAME = '{http://owncloud.org/ns}shares';

	/**
	 * Reference to main server object
	 *
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var \OCP\Share\IManager
	 */
	private $shareManager;

	/**
	 * @var \Sabre\DAV\Tree
	 */
	private $tree;

	/**
	 * @var string
	 */
	private $userId;

	/**
	 * @var \OCP\Files\Folder
	 */
	private $userFolder;

	/**
	 * @var IShare[]
	 */
	private $cachedShares;

	/**
	 * @param \Sabre\DAV\Tree $tree tree
	 * @param \OCP\ITagManager $tagManager tag manager
	 */
	public function __construct(
		\Sabre\DAV\Tree $tree,
		IUserSession $userSession,
		\OCP\Files\Folder $userFolder,
		\OCP\Share\IManager $shareManager
	) {
		$this->tree = $tree;
		$this->shareManager = $shareManager;
		$this->userFolder = $userFolder;
		$this->userId = $userSession->getUser()->getUID();
		$this->cachedShares = [];
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by \Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server) {

		$server->xml->namespacesMap[self::NS_OWNCLOUD] = 'oc';
		// FIXME
		//$server->xml->elementMap[self::SHARES_PROPERTYNAME] = 'OCA\\DAV\\Connector\\Sabre\\SharesList';

		$this->server = $server;
		$this->server->on('propFind', array($this, 'handleGetProperties'));
	}

	/**
	 * Returns shares for the given file id
	 *
	 * @param \OCP\Files\Node $node node for which to retrieve shares
	 *
	 * @return IShare list of shares
	 */
	private function getShares($node) {
		$shares = [];
		$shareTypes = [0, 1, 3]; // FIXME: use constants
		foreach ($shareTypes as $shareType) {
			// one of each type is enough to find out about the types
			$shares = array_merge($shares, $this->shareManager->getSharesBy($this->userId, $shareType, $node, false, 1));
		}
		return $shares;
	}

	/**
	 * Adds shares to propfind response
	 *
	 * @param PropFind $propFind
	 * @param \Sabre\DAV\INode $node
	 * @return void
	 */
	public function handleGetProperties(
		PropFind $propFind,
		\Sabre\DAV\INode $sabreNode
	) {
		if (!($sabreNode instanceof \OCA\DAV\Connector\Sabre\Node)) {
			return;
		}

		// need prefetch ?
		if ($sabreNode instanceof \OCA\DAV\Connector\Sabre\Directory
			&& $propFind->getDepth() !== 0
			&& !is_null($propFind->getStatus(self::SHARES_PROPERTYNAME))
		) {
			$folderNode = $this->userFolder->get($propFind->getPath());
			$children = $folderNode->getDirectoryListing();

			foreach ($children as $childNode) {
				$this->cachedShares[$childNode->getId()] = $this->getShares($childNode);
			}
		}

		$propFind->handle(self::SHARES_PROPERTYNAME, function() use ($sabreNode) {
			if (isset($this->cachedShares[$sabreNode->getId()])) {
				$shares = $this->cachedShares[$sabreNode->getId()];
			} else {
				$node = $this->userFolder->get($sabreNode->getPath());
				$shares = $this->getShares($node);
			}

			$shareTypes = [];
			foreach ($shares as $share) {
				$shareTypes[] = $share->getShareType();
			}

			// FIXME: decide on the response format
			if (!empty($shareTypes)) {
				return json_encode($shareTypes);
			}
			return null;
		});
	}
}
