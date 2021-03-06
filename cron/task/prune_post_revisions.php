<?php
/**
 *
 * Prime Post Revisions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, Ken F. Innes IV
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace primehalo\primepostrevisions\cron\task;

/**
 * Prime Post Revisions cron task.
 */
class prune_post_revisions extends \phpbb\cron\task\base
{

	protected $cron_frequency = 86400;	// How often we run the cron (in seconds), 86400 seconds = 24 hours
	protected $config;
	protected $db;
	protected $phpbb_log;
	protected $user;
	protected $revisions_table;

	/**
	* Constructor
	*
	* @param \phpbb\config\config				$config 			Config object
	* @param \phpbb\db\driver\driver_interface	$db					Database connection
	* @param \phpbb\log\log_interface			$phpbb_log			Log
	* @param \phpbb\user						$user				User object
 	* @param string								$revisions_table	Prime Post Revisions table
	*/
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $phpbb_log,
		\phpbb\user $user,
		$revisions_table)
	{
		$this->config			= $config;
		$this->db				= $db;
		$this->phpbb_log		= $phpbb_log;
		$this->user				= $user;
		$this->revisions_table	= $revisions_table;
	}

	/**
	* Runs this cron task.
	*
	* @return void
	*/
	public function run()
	{
		// Run your cron actions here...
		$del_cnt	= 0;
		$sql		= 'SELECT forum_id, primepostrev_autoprune FROM ' . FORUMS_TABLE . ' WHERE primepostrev_autoprune > 0';
		$result		= $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$del_cnt += $this->prune_forum($row['forum_id'], $row['primepostrev_autoprune']);
		}
		$this->db->sql_freeresult($result);

		// Log the auto-pruning result
		$this->user->add_lang_ext('primehalo/primepostrevisions', 'info_acp_main');
		$this->phpbb_log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PRIMEPOSTREVISIONS_AUTOPRUNE', false, array($del_cnt));

		// Update the cron task run time here if it hasn't
		// already been done by your cron actions.
		$this->config->set('primepostrev_cron_last_run', time(), false);
	}

	/**
	* Deletes revisions from posts in the given forum that are older than the given number of days
	*
	* @param int $forum_id		The ID of the forum
	* @param int $prune_days	The age in day of revisions to be deleted
	* @return int The number of revisions deleted
	*/
	protected function prune_forum($forum_id, $prune_days)
	{
		if ($forum_id && $prune_days)
		{
			$rev_list	= array();
			$prune_date	= time() - ($prune_days * 86400);	// 86400 seconds = 24 hours
			$sql = 'SELECT r.revision_id
					FROM ' . $this->revisions_table . ' r, ' . POSTS_TABLE . ' p
					WHERE p.forum_id = ' . $forum_id . ' AND p.post_id = r.post_id AND r.revision_time < ' . $prune_date;
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$rev_list[] = $row['revision_id'];
			}
			$this->db->sql_freeresult($result);
			$rev_list = array_unique($rev_list);

			if (!empty($rev_list))
			{
				$sql = "DELETE FROM {$this->revisions_table} WHERE " . $this->db->sql_in_set('revision_id', $rev_list);
				$this->db->sql_query($sql);
				return $this->db->sql_affectedrows();
			}
		}
		return 0;
	}


	/**
	* Returns whether this cron task can run, given current board configuration.
	*
	* For example, a cron task that prunes forums can only run when
	* forum pruning is enabled.
	*
	* @return bool
	*/
	public function is_runnable()
	{
		return true;
	}

	/**
	* Returns whether this cron task should run now, because enough time
	* has passed since it was last run.
	*
	* @return bool
	*/
	public function should_run()
	{
		return $this->config['primepostrev_cron_last_run'] < time() - $this->cron_frequency;
	}
}
