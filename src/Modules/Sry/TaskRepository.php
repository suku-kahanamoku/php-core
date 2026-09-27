<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class TaskRepository extends SryRepository
{
    public function earnedPoints(int $memberId, string $date): ?array
    {
        return $this->_db->fetchOne(
            "SELECT COALESCE(SUM(points),0) AS total FROM task_points WHERE member_id=? AND earned_on=?",
            [$memberId, $date],
        ) ?:
            null;
    }
    public function submissions(int $id, int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT s.id,s.media_id,s.note,s.created_at,r.decision,r.note AS review_note FROM task_submission s LEFT JOIN task_review r ON r.submission_id=s.id WHERE s.assignment_id=? AND s.family_id=? ORDER BY s.id DESC",
            [$id, $familyId],
        );
    }
    public function media(int $taskId, int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT media_id FROM task_media WHERE task_id=? AND family_id=?",
            [$taskId, $familyId],
        );
    }
    public function createTask(array $data): int
    {
        return $this->_db->insert("tasks", $data);
    }
    public function attachMedia(array $data): int
    {
        return $this->_db->insert("task_media", $data);
    }
    public function assign(array $data): int
    {
        return $this->_db->insert("task_assignment", $data);
    }
    public function createSubmission(array $data): int
    {
        return $this->_db->insert("task_submission", $data);
    }
    public function markSubmitted(int $submissionId, int $id): void
    {
        $this->_db->query(
            "UPDATE task_assignment SET status='submitted',current_submission_id=?,revision=revision+1 WHERE id=?",
            [$submissionId, $id],
        );
    }
    public function createReview(array $data): int
    {
        return $this->_db->insert("task_review", $data);
    }
    public function markReviewed(string $decision, int $id): void
    {
        $this->_db->query(
            "UPDATE task_assignment SET status=?,revision=revision+1 WHERE id=?",
            [$decision, $id],
        );
    }
    public function awardPoints(array $data): int
    {
        return $this->_db->insert("task_points", $data);
    }
    public function forFamily(int $familyId, ?int $memberId): array
    {
        $scope = $memberId === null ? "" : " AND a.member_id=?";
        $args = $memberId === null ? [$familyId] : [$familyId, $memberId];
        return $this->_db->fetchAll(
            "SELECT a.id,a.task_id,a.member_id,a.due_date,a.points,a.status,a.revision,a.current_submission_id,t.title,t.description,t.category_id,t.enumeration_id,m.name AS member_name FROM task_assignment a JOIN tasks t ON t.id=a.task_id JOIN sry_member m ON m.id=a.member_id WHERE a.family_id=?$scope ORDER BY a.due_date DESC,a.id DESC LIMIT 200",
            $args,
        );
    }
    public function findAssignment(int $id, int $familyId, bool $lock): ?array
    {
        return $this->_db->fetchOne(
            "SELECT a.*,t.title,t.description,m.name AS member_name FROM task_assignment a JOIN tasks t ON t.id=a.task_id JOIN sry_member m ON m.id=a.member_id WHERE a.id=? AND a.family_id=?" .
                ($lock ? " FOR UPDATE" : ""),
            [$id, $familyId],
        ) ?:
            null;
    }
}
