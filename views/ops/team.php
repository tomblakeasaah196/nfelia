<?php
/** The Ops team (list): $team, $me. Invite opens a dialog. */
$status = function (array $s): array {
    if (!(int)$s['is_active'])        return ['Off the team', 'muted'];
    if ($s['password_hash'] === null) return [(int)$s['invite_live'] ? 'Invited' : 'Invite expired', 'warn'];
    return ['Active', 'ok'];
};
?>
<div class="ops-head">
    <div>
        <h1>Team</h1>
        <p>Who can use Nfelia Ops, and what their role lets them do. Taking someone off the team signs them out everywhere at once.</p>
    </div>
    <div class="ops-head-actions">
        <button type="button" class="ops-btn ops-btn-primary" data-ops-dialog="invite"><?= ops_icon('plus') ?> Invite someone</button>
    </div>
</div>

<div class="ops-toolbar">
    <div class="ops-search">
        <?= ops_icon('search') ?>
        <label class="sr-only" for="team-search">Search the team</label>
        <input class="ops-input" id="team-search" type="search" placeholder="Search by name or email" data-ops-filter="#team-table tbody tr">
    </div>
</div>

<div class="ops-table-wrap">
    <table class="ops-table is-stacked" id="team-table">
        <thead>
            <tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last signed in</th><th scope="col">Passkeys</th><th scope="col" class="cell-actions"><span class="sr-only">Actions</span></th></tr>
        </thead>
        <tbody>
        <?php foreach ($team as $s): [$label, $tone] = $status($s); $is_me = (int)$s['id'] === (int)$me['id']; ?>
            <tr data-filter-text="<?= e(mb_strtolower($s['full_name'] . ' ' . $s['email'])) ?>">
                <td>
                    <span class="cell-main"><?= e($s['full_name']) ?><?= $is_me ? ' (you)' : '' ?></span>
                    <span class="cell-sub"><?= e($s['email']) ?></span>
                </td>
                <td data-label="Role">
                    <?php if ($is_me || !(int)$s['is_active']): ?>
                        <?= e(ops_role_label($s['role'])) ?>
                    <?php else: ?>
                        <form method="post" action="<?= e(ops_url('team/' . (int)$s['id'] . '/role')) ?>" data-ops-confirm="Change <?= e($s['full_name']) ?>'s role?">
                            <?= ops_csrf_field() ?>
                            <label class="sr-only" for="role-<?= (int)$s['id'] ?>">Role of <?= e($s['full_name']) ?></label>
                            <select class="ops-select" id="role-<?= (int)$s['id'] ?>" name="role" style="min-height:36px;padding:6px 10px;width:auto" data-ops-autosubmit>
                                <?php foreach (OPS_ROLES as $key => $r): ?>
                                    <option value="<?= e($key) ?>"<?= $s['role'] === $key ? ' selected' : '' ?>><?= e($r['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </td>
                <td data-label="Status"><span class="ops-chip ops-chip-<?= e($tone) ?>"><?= e($label) ?></span></td>
                <td class="num" data-label="Last signed in"><?= $s['last_signin_at'] ? e(ops_ago($s['last_signin_at'])) : '<span class="muted">Never</span>' ?></td>
                <td class="num" data-label="Passkeys"><?= (int)$s['passkeys'] ?: '<span class="muted">None</span>' ?></td>
                <td class="cell-actions">
                    <?php if (!$is_me): ?>
                        <?php if ((int)$s['is_active'] && $s['password_hash'] === null): ?>
                            <form method="post" action="<?= e(ops_url('team/' . (int)$s['id'] . '/resend')) ?>" style="display:inline">
                                <?= ops_csrf_field() ?>
                                <button class="ops-btn ops-btn-ghost ops-btn-sm" type="submit">Send a new invite</button>
                            </form>
                        <?php endif; ?>
                        <?php if ((int)$s['is_active']): ?>
                            <form method="post" action="<?= e(ops_url('team/' . (int)$s['id'] . '/deactivate')) ?>" style="display:inline"
                                  data-ops-confirm="Take <?= e($s['full_name']) ?> off the team? They are signed out everywhere at once.">
                                <?= ops_csrf_field() ?>
                                <button class="ops-btn ops-btn-danger ops-btn-sm" type="submit">Take off the team</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(ops_url('team/' . (int)$s['id'] . '/reactivate')) ?>" style="display:inline">
                                <?= ops_csrf_field() ?>
                                <button class="ops-btn ops-btn-secondary ops-btn-sm" type="submit">Bring back</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr data-ops-filter-empty hidden><td colspan="6"><div class="ops-empty"><h3>Nobody matches</h3><p>Try another name or email.</p></div></td></tr>
        </tbody>
    </table>
    <div class="ops-pager">
        <span class="num"><?= count($team) ?> <?= count($team) === 1 ? 'person' : 'people' ?></span>
        <span class="ops-actions">
            <span class="ops-btn ops-btn-secondary ops-btn-sm" aria-disabled="true">Previous</span>
            <span class="ops-btn ops-btn-secondary ops-btn-sm" aria-disabled="true">Next</span>
        </span>
    </div>
</div>

<dialog class="ops-dialog" id="invite" aria-labelledby="invite-title">
    <form method="post" action="<?= e(ops_url('team/invite')) ?>">
        <?= ops_csrf_field() ?>
        <div class="ops-dialog-head">
            <h2 id="invite-title">Invite someone</h2>
            <button type="button" data-ops-close aria-label="Close"><?= ops_icon('close') ?></button>
        </div>
        <div class="ops-dialog-body">
            <div class="ops-field">
                <label for="inv-name">Name</label>
                <input class="ops-input" id="inv-name" name="full_name" autocomplete="off" required>
            </div>
            <div class="ops-field">
                <label for="inv-email">Email</label>
                <input class="ops-input" id="inv-email" name="email" type="email" autocomplete="off" required>
                <span class="hint">They get a link to choose a password. It works for 72 hours.</span>
            </div>
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="ops-label" style="margin-bottom:8px">Role</legend>
                <?php foreach (OPS_ROLES as $key => $r): ?>
                    <label class="ops-radio">
                        <input type="radio" name="role" value="<?= e($key) ?>"<?= $key === 'support' ? ' checked' : '' ?>>
                        <span><b><?= e($r['label']) ?></b><small><?= e($r['hint']) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        </div>
        <div class="ops-dialog-foot">
            <button type="button" class="ops-btn ops-btn-secondary" data-ops-close>Cancel</button>
            <button type="submit" class="ops-btn ops-btn-primary">Send the invite</button>
        </div>
    </form>
</dialog>
