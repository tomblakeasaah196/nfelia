<?php
/** Writing or changing a campaign (form): $c (id null: new). */
$aud = json_decode((string)$c['audience'], true) ?: [];
$action = $c['id'] ? ops_url('campaigns/' . (int)$c['id']) : ops_url('campaigns');
?>
<p class="ops-back"><a href="<?= e(ops_url($c['id'] ? 'campaigns/' . (int)$c['id'] : 'campaigns')) ?>">← <?= $c['id'] ? e($c['name']) : 'Campaigns' ?></a></p>
<div class="ops-head"><div><h1><?= $c['id'] ? 'Change the campaign' : 'Write a campaign' ?></h1>
    <p>Short and useful: what Nfelia does for them, one thing to do next. You'll see who it reaches and how it looks before anything is sent.</p></div></div>
<form method="post" action="<?= e($action) ?>" class="ops-card"><div class="ops-card-body">
    <?= ops_csrf_field() ?>
    <div class="ops-field"><label for="c-name">Name <span class="muted">(only the team sees it)</span></label><input class="ops-input" id="c-name" name="name" value="<?= e($c['name']) ?>" required maxlength="120" placeholder="Trials ending, October"></div>
    <div class="ops-field"><label for="c-subject">Subject</label><input class="ops-input" id="c-subject" name="subject" value="<?= e($c['subject']) ?>" required maxlength="160" placeholder="Your free trial ends soon, {first_name}"></div>
    <div class="ops-field"><label for="c-body">Message</label><textarea class="ops-textarea" id="c-body" name="body" rows="10" required><?= e($c['body']) ?></textarea>
        <span class="hint">Plain words. A blank line starts a new paragraph. {first_name} and {business} are filled in for each person.</span></div>
    <div class="ops-row">
        <div class="ops-field"><label for="c-bt">Button <span class="muted">(optional)</span></label><input class="ops-input" id="c-bt" name="button_text" value="<?= e($c['button_text'] ?? '') ?>" maxlength="60" placeholder="Open Nfelia"></div>
        <div class="ops-field"><label for="c-bu">Button link</label><input class="ops-input" id="c-bu" name="button_url" type="url" value="<?= e($c['button_url'] ?? '') ?>" placeholder="https://nfelia.com/settings?tab=billing"></div>
    </div>
    <fieldset class="ops-fieldset">
        <legend class="ops-label">Who it goes to</legend>
        <p class="hint" style="margin-bottom:10px">Each email address gets it once, and nobody who unsubscribed.</p>
        <div class="ops-checks-grid">
            <div><b class="small">Leads at</b>
                <?php foreach (LEAD_STAGES as $k => $s): ?><label class="ops-check"><input type="checkbox" name="leads[]" value="<?= e($k) ?>"<?= in_array($k, $aud['leads'] ?? [], true) ? ' checked' : '' ?>><span><?= e($s['label']) ?></span></label><?php endforeach; ?></div>
            <div><b class="small">Clients</b>
                <?php foreach (CAMPAIGN_CLIENT_GROUPS as $k => $label): ?><label class="ops-check"><input type="checkbox" name="clients[]" value="<?= e($k) ?>"<?= in_array($k, $aud['clients'] ?? [], true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div>
        </div>
    </fieldset>
    <div class="ops-actions" style="margin-top:16px"><button class="ops-btn ops-btn-primary" type="submit">Save, then check it</button>
        <a class="ops-btn ops-btn-ghost" href="<?= e(ops_url($c['id'] ? 'campaigns/' . (int)$c['id'] : 'campaigns')) ?>">Cancel</a></div>
</div></form>
