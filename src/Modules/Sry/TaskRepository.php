<?php
declare(strict_types=1);
namespace App\Modules\Sry;
/**
 * Úkoly, přiřazení členům, odevzdání, recenze a body.
 *
 * Každý dotaz je omezen `family_id`, aby nebylo možné se dostat k úkolům jiné
 * rodiny. Přiřazení se mění přes `revision`, aby se dala sledovat historie
 * stavu; `findAssignment()` umí načíst řádek pod zámkem (`FOR UPDATE`) pro
 * bezpečné souběžné zpracování.
 */
final class TaskRepository extends SryRepository
{
    /**
     * Součet bodů člena za daný den.
     *
     * @param  int    $memberId ID člena.
     * @param  string $date     Datum ve formátu `Y-m-d`.
     * @return array<string, mixed>|null `{ total }`, nebo null pokud řádek chybí.
     */
    public function earnedPoints(int $memberId, string $date): ?array
    {
        return $this->_db->fetchOne(
            "SELECT COALESCE(SUM(points),0) AS total FROM task_points WHERE member_id=? AND earned_on=?",
            [$memberId, $date],
        ) ?:
            null;
    }
    /**
     * Odevzdání jednoho přiřazení včetně poslední recenze.
     *
     * @param  int $id       ID přiřazení.
     * @param  int $familyId ID rodiny.
     * @return list<array<string, mixed>> Odevzdání od nejnovějšího.
     */
    public function submissions(int $id, int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT s.id,s.media_id,s.note,s.created_at,r.decision,r.note AS review_note FROM task_submission s LEFT JOIN task_review r ON r.submission_id=s.id WHERE s.assignment_id=? AND s.family_id=? ORDER BY s.id DESC",
            [$id, $familyId],
        );
    }
    /**
     * Média přiřazená k úkolu v rodině.
     *
     * @param  int $taskId   ID úkolu.
     * @param  int $familyId ID rodiny.
     * @return list<array<string, mixed>> Seznam `{ media_id }`.
     */
    public function media(int $taskId, int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT media_id FROM task_media WHERE task_id=? AND family_id=?",
            [$taskId, $familyId],
        );
    }
    /**
     * Vloží definici úkolu.
     *
     * @param  array<string, mixed> $data Atributy úkolu včetně `family_id`.
     * @return int                       ID vloženého úkolu.
     */
    public function createTask(array $data): int
    {
        return $this->_db->insert("tasks", $data);
    }
    /**
     * Připojí medium k úkolu.
     *
     * @param  array<string, mixed> $data Atributy vazby včetně `task_id`, `media_id` a `family_id`.
     * @return int                       ID vložené vazby.
     */
    public function attachMedia(array $data): int
    {
        return $this->_db->insert("task_media", $data);
    }
    /**
     * Vytvoří přiřazení úkolu členu.
     *
     * @param  array<string, mixed> $data Atributy přiřazení včetně `task_id`, `member_id` a `family_id`.
     * @return int                       ID vloženého přiřazení.
     */
    public function assign(array $data): int
    {
        return $this->_db->insert("task_assignment", $data);
    }
    /**
     * Vytvoří odevzdání (novou revizi) k přiřazení.
     *
     * @param  array<string, mixed> $data Atributy odevzdání včetně `assignment_id` a `media_id`.
     * @return int                       ID vloženého odevzdání.
     */
    public function createSubmission(array $data): int
    {
        return $this->_db->insert("task_submission", $data);
    }
    /**
     * Označí přiřazení jako odevzdané a naváže poslední odevzdání.
     *
     * @param  int $submissionId ID odevzdání.
     * @param  int $id           ID přiřazení.
     * @return void              Vedlejší efekt: stav `submitted` a zvýšení `revision`.
     */
    public function markSubmitted(int $submissionId, int $id): void
    {
        $this->_db->query(
            "UPDATE task_assignment SET status='submitted',current_submission_id=?,revision=revision+1 WHERE id=?",
            [$submissionId, $id],
        );
    }
    /**
     * Zapíše recenzi rodiče k odevzdání.
     *
     * @param  array<string, mixed> $data Atributy recenze včetně `submission_id` a `decision`.
     * @return int                       ID vložené recenze.
     */
    public function createReview(array $data): int
    {
        return $this->_db->insert("task_review", $data);
    }
    /**
     * Nastaví stav přiřazení podle rozhodnutí recenze.
     *
     * @param  string $decision Výsledek recenze (např. `approved` nebo `rejected`).
     * @param  int    $id       ID přiřazení.
     * @return void             Vedlejší efekt: zápis stavu a zvýšení `revision`.
     */
    public function markReviewed(string $decision, int $id): void
    {
        $this->_db->query(
            "UPDATE task_assignment SET status=?,revision=revision+1 WHERE id=?",
            [$decision, $id],
        );
    }
    /**
     * Zapíše body za splněný úkol.
     *
     * @param  array<string, mixed> $data Atributy bodů včetně `member_id`, `points` a `earned_on`.
     * @return int                       ID vloženého záznamu.
     */
    public function awardPoints(array $data): int
    {
        return $this->_db->insert("task_points", $data);
    }
    /**
     * Přiřazení úkolů v rodině, volitelně jen jednoho člena.
     *
     * @param  int      $familyId ID rodiny.
     * @param  int|null $memberId ID člena, nebo null pro celou rodinu.
     * @return list<array<string, mixed>> Přiřazení se stavem a názvem úkolu, max. 200.
     */
    public function forFamily(int $familyId, ?int $memberId): array
    {
        $scope = $memberId === null ? "" : " AND a.member_id=?";
        $args = $memberId === null ? [$familyId] : [$familyId, $memberId];
        return $this->_db->fetchAll(
            "SELECT a.id,a.task_id,a.member_id,a.due_date,a.points,a.status,a.revision,a.current_submission_id,t.title,t.description,t.category_id,t.enumeration_id,m.name AS member_name FROM task_assignment a JOIN tasks t ON t.id=a.task_id JOIN sry_member m ON m.id=a.member_id WHERE a.family_id=?$scope ORDER BY a.due_date DESC,a.id DESC LIMIT 200",
            $args,
        );
    }
    /**
     * Načte přiřazení s úkolem a jménem člena, volitelně pod zámkem.
     *
     * @param  int  $id       ID přiřazení.
     * @param  int  $familyId ID rodiny (musí souhlasit).
     * @param  bool $lock     true přidá `FOR UPDATE` pro bezpečnou souběžnou změnu.
     * @return array<string, mixed>|null Řádek přiřazení, nebo null.
     */
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
