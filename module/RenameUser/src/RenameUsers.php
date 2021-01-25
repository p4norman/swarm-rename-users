<?php
/**
 * Perforce Swarm 2020.1 Extension for RenameUser
 */

namespace RenameUser;

use Application\Factory\InvokableService;
use Interop\Container\ContainerInterface;


/**
 * Service to rename batches of users
 * @package Users\Helper
 */
class RenameUsers implements InvokableService
{
    const RENAME = 'rename';
    const CONSOLE = 'console';

    const BEFORE = "*** Before";
    const AFTER = "*** After ";

    private $services;
    private $p4admin;
    private $logfile;
    private $warningcount;

    private $verbose = false;
    private $preview = false;
    private $logtofile = false;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $services, array $options = null)
    {
        $this->services = $services;
    }

    public function logLine($string)
    {
        if ($this->logtofile) {
            fwrite($this->logfile, $string . "\n");
        }
    }

    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    public function setPreview($preview)
    {
        $this->preview = $preview;
    }

    public function setLogToFile($logtofile)
    {
        $this->logtofile = $logtofile;
    }

    // check that all the target usernames exist in Perforce
    // it is just a warning, to help admins weed out typos in their users.php
    public function checkUsers($usermap)
    {
        if (! $this->preview)
            return;

        $this->warningcount = 0;
        $userdetails = $this->p4admin->run('users', ['-a']);

        // Get a simple list of all the users
        $userlist = array();
        foreach($userdetails->getData() as $userdetail){
            $userlist[] = $userdetail['User'];
        }

        // Check each newname against the user list
        foreach ($usermap as $oldname => $newname) {
            if (!in_array($newname, $userlist)) {
                $this->warningcount++;
                $this->logLine("WARNING! Target Username " . $newname . " is not a current Perforce user");
            }
        }
        if ($this->warningcount)
            $this->logLine("");
    }

    /*
     * Given a usermap array of [ oldname => newname, ... ]
     * go through swarm data structures replacing oldname with newname.
     */
    public function rename($usermap)
    {
        $this->p4admin = $this->services->get('p4_admin');

        $this->logfile = fopen(BASE_PATH . '/data/rename.log', 'a');
        if (! $this->logfile){
            return false;
        }
        if ($this->preview) {
            $this->logLine("\nRename Users: PREVIEW");
        } else {
            $this->logLine("\nRename Users: MAKING CHANGES");
        }

        $this->logLine("Usermap:  " . print_r($usermap, true));

        $this->checkUsers($usermap);

        $this->fixReviews($usermap);
        $this->fixActivities($usermap);
        $this->fixComments($usermap);
        $this->fixProjects($usermap);
        $this->fixWorkflows($usermap);

        if ($this->preview) {
            $this->logLine("Rename Users: PREVIEW COMPLETE");
            if ($this->warningcount > 0)
            {
                $this->logLine("WARNING!  " . $this->warningcount . " target user names do not exist in Perforce!");
            }
            $this->logLine("This was PREVIEW mode, rerun this command with the '-Y' option to actually make changes to Swarm data\n");
        } else {
            $this->logLine("Rename Users: CHANGES COMPLETE\n");
        }
        fclose($this->logfile);
        return true;
    }

    // print the interesting parts of a review as JSON
    function echoReview($label, $review)
    {
        if (!$this->logtofile)
            return "";

        $id = $review->getId();
        $author = $review->get('author');
        $participants = $review->get('participants');
        $versions = $review->get('versions');

        $review_contents = (object)["id" => $id, "author" => $author, "participants" => $participants, "versions" => $versions];

        return ($label . " " . json_encode($review_contents));
    }

    function fixReviews($usermap)
    {
        $this->logLine("--- REVIEWS ---");

        $reviews = \Reviews\Model\Review::fetchAll(array(), $this->p4admin);

        $count = 0;
        $changed = 0;

        foreach ($reviews as $review) {
            $author = $review->get('author');
            $participants = $review->get('participants');
            $versions = $review->get('versions');

            $before = $this->echoReview(self::BEFORE, $review);

            $reviewChanged = false;

            if (array_key_exists($author, $usermap)) {
                $review->set('author', $usermap[$author]);
                $reviewChanged = true;
            }

            $newParticipants = array();
            foreach ($participants as $participant) {
                if (array_key_exists($participant, $usermap)) {
                    $newParticipants[] = $usermap[$participant];
                    $reviewChanged = true;
                } else {
                    $newParticipants[] = $participant;
                }
            }

            $review->set('participants', $newParticipants);

            $newVersions = array();
            foreach ($versions as $version) {
                if (array_key_exists('user', $version) &&
                    array_key_exists($version['user'], $usermap)) {
                    $version['user'] = $usermap[$version['user']];
                    $reviewChanged = true;
                }
                $newVersions[] = $version;
            }
            $review->set('versions', $newVersions);

            // save the changed review
            if ($reviewChanged) {
                $changed++;
                $this->logLine($before);
                $after = $this->echoReview(self::AFTER, $review);
                $this->logLine($after . "\n");

                if (!$this->preview) {
                    $review->save();
                }
            }

            $count++;
            if (fmod($count, 100) == 0) {
                $this->logLine($count . " reviews processed ...");
            }
        }

        $this->logLine("Processed total of " . $count . " reviews. " . $changed . " were modified\n\n");
    }

    // report the interesting parts of an Activity entry as JSON
    function echoActivity($label, $activity)
    {
        if (!$this->logtofile)
            return "";

        $id = $activity->getId();
        $user = $activity->get('user');
        $followers = $activity->getFollowers();

        $activity_contents = (object)["id" => $id, "user" => $user, "followers" => $followers];
        return ($label . " " . json_encode($activity_contents));
    }

    function fixActivities($usermap)
    {
        $this->logLine("--- ACTIVITIES ---");

        $activities = \Activity\Model\Activity::fetchAll(array(), $this->p4admin);

        $count = 0;
        $changed = 0;
        foreach ($activities as $activity) {
            $activityChanged = false;

            $before = $this->echoActivity(self::BEFORE, $activity);

            $user = $activity->get('user');

            if (array_key_exists($user, $usermap)) {
                $activity->set('user', $usermap[$user]);
                $activityChanged = true;
            }

            $followers = $activity->getFollowers();
            $newFollowers = array();

            foreach ($followers as $follower) {
                if (array_key_exists($follower, $usermap)) {
                    $newFollowers[] = $usermap[$follower];
                    $activityChanged = true;
                } else {
                    $newFollowers[] = $follower;
                }
            }
            $activity->setFollowers($newFollowers);

            if ($activityChanged) {
                $changed++;
                $this->logLine($before);
                $after = $this->echoActivity(self::AFTER, $activity);
                $this->logLine($after . "\n");

                if (!$this->preview) {
                    $activity->save();
                }
            }

            $count++;
            if (fmod($count, 100) == 0) {
                $this->logLine($count . " activities processed ...");
            }
        }
        $this->logLine("Processed total of " . $count . " activities. " . $changed . " were modified \n\n");
    }

    // report the interesting parts of an Comment entry as JSON
    function echoComment($label, $comment)
    {
        if (!$this->logtofile)
            return "";

        $id = $comment->getId();
        $user = $comment->get('user');
        $likes = $comment->getLikes();
        $readBy = $comment->getReadBy();

        $comment_contents = (object)["id" => $id, "user" => $user, "likes" => $likes, "readby" => $readBy];
        return ($label . " " . json_encode($comment_contents));
    }

    function fixComments($usermap)
    {
        $this->logLine("--- COMMENTS ---");

        $comments = \Comments\Model\Comment::fetchAll(array(), $this->p4admin);

        $count = 0;
        $changed = 0;
        foreach ($comments as $comment) {
            $commentChanged = false;

            $before = $this->echoComment(self::BEFORE, $comment);

            $user = $comment->get('user');

            if (array_key_exists($user, $usermap)) {
                $comment->set('user', $usermap[$user]);
                $commentChanged = true;
            }

            $likes = $comment->getLikes();
            $newLikes = array();

            foreach ($likes as $like) {
                if (array_key_exists($like, $usermap)) {
                    $newLikes[] = $usermap[$like];
                    $commentChanged = true;
                } else {
                    $newLikes[] = $like;
                }
            }
            $comment->setLikes($newLikes);

            $readby = $comment->getReadBy();
            $newReadby = array();

            foreach ($readby as $reader) {
                if (array_key_exists($reader, $usermap)) {
                    $newReadby[] = $usermap[$reader];
                    $commentChanged = true;
                } else {
                    $newReadby[] = $reader;
                }
            }
            $comment->setReadby($newReadby);

            if ($commentChanged) {
                $changed++;
                $this->logLine($before);
                $after = $this->echoComment(self::AFTER, $comment);
                $this->logLine($after . "\n");

                if (!$this->preview) {
                    $comment->save();
                }
            }

            $count++;
            if (fmod($count, 100) == 0) {
                $this->logLine($count . " comments processed ...");
            }
        }
        $this->logLine("Processed total of " . $count . " comments. " . $changed . " were modified\n\n");
    }

    // report the interesting parts of an Project entry as JSON
    function echoProject($label, $project)
    {
        if (!$this->logtofile)
            return "";

        $id = $project->getId();
        $members = $project->getMembers();
        $owners = $project->getOwners();
        $branches = $project->getBranches();

        $project_contents = (object)["id" => $id, "members" => $members, "owners" => $owners, "branches" => $branches];
        return ($label . " " . json_encode($project_contents));
    }

    function fixProjects($usermap)
    {
        $this->logLine("--- PROJECTS ---");

        $projects = \Projects\Model\Project::fetchAll(array(), $this->p4admin);

        $count = 0;
        $changed = 0;
        foreach ($projects as $project) {
            $projectChanged = false;

            $before = $this->echoProject(self::BEFORE, $project);
            $members = $project->getMembers();
            $newMembers = array();

            foreach ($members as $member) {
                if (array_key_exists($member, $usermap)) {
                    $newMembers[] = $usermap[$member];
                    $projectChanged = true;
                } else {
                    $newMembers[] = $member;
                }
            }

            $project->setMembers($newMembers);

            $owners = $project->getOwners();
            $newOwners = array();

            foreach ($owners as $owner) {
                if (array_key_exists($owner, $usermap)) {
                    $newOwners[] = $usermap[$owner];
                    $projectChanged = true;
                } else {
                    $newOwners[] = $owner;
                }
            }

            $project->setOwners($newOwners);

            // checking branch moderators
            $branches = $project->getBranches();
            $newBranches = array();

            foreach ($branches as $branch) {
                if (array_key_exists('moderators', $branch) && !empty($branch['moderators'])) {
                    $newModerators = array();
                    $moderators = $branch['moderators'];

                    foreach ($moderators as $moderator) {
                        if (array_key_exists($moderator, $usermap)) {
                            $newModerators[] = $usermap[$moderator];
                            $projectChanged = true;
                        } else {
                            $newModerators[] = $moderator;
                        }
                    }

                    $branch['moderators'] = $newModerators;
                }

                $newBranches[] = $branch;
            }

            $project->SetBranches($newBranches);

            if ($projectChanged) {
                $changed++;
                $this->logLine($before);
                $after = $this->echoProject(self::AFTER, $project);
                $this->logLine($after . "\n");

                if (!$this->preview) {
                    $project->save();
                }
            }

            $count++;
            if (fmod($count, 10) == 0) {
                $this->logLine($count . " projects processed");
            }
        }
        $this->logLine("Processed total of " . $count . " projects. " . $changed . " were modified\n\n");
    }

    // print the interesting parts of a workflow as JSON
    function echoWorkflow($label, $workflow)
    {
        if (!$this->logtofile)
            return "";

        $id = $workflow->getId();
        $owners = $workflow->getOwners();
        $exclusions = $workflow->GetUserExclusions();

        $workflow_contents = (object)["id" => $id, "owners" => $owners, "exclusions" => $exclusions];

        return ($label . " " . json_encode($workflow_contents));
    }

    function fixWorkflows($usermap)
    {
        $this->logLine("--- WORKFLOWS ---");

        $workflows = \Workflow\Model\Workflow::fetchAll(array(), $this->p4admin);

        $count = 0;
        $changed = 0;
        foreach ($workflows as $workflow) {
            $workflowChanged = false;

            $before = $this->echoWorkflow(self::BEFORE, $workflow);

            $owners = $workflow->getOwners();
            $newOwners = array();

            foreach ($owners as $owner) {
                if (array_key_exists($owner, $usermap)) {
                    $newOwners[] = $usermap[$owner];
                    $workflowChanged = true;
                } else {
                    $newOwners[] = $owner;
                }
            }
            if ($workflowChanged) {
                $workflow->setOwners($newOwners);
            }

            $user_exclusions = $workflow->GetUserExclusions();
            $newExclusions = array();

            if ($workflowChanged) {
                $changed++;
                $this->logLine($before);
                $after = $this->echoWorkflow(self::AFTER, $workflow);
                $this->logLine($after . "\n");
                if (!$this->preview) {
                    $workflow->save();
                }
            }

            $count++;
            if (fmod($count, 10) == 0) {
                $this->logLine($count . " workflows processed");
            }
        }
        $this->logLine("Processed total of " . $count . " workflows. " . $changed . " were modified\n\n");
    }
}

