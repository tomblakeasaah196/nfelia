<?php
/**
 * The name a client sees when someone on the Nfelia team writes to them or
 * acts on their account.
 *
 * The founder works in Ops too, but clients should hear from Customer
 * Support, not from him (the owner, 27 Nov 2026). Whatever he sends from
 * Ops is signed by Simi, of the support team; a notice about something he
 * did (opening an account read-only, making a Records Book) says "Customer
 * Support", which is true and names nobody. Ops' own audit log always keeps
 * who really did it.
 *
 * .env can say it for anyone, replacing the rule below:
 *   OPS_PUBLIC_AS="Tom-Blake=Simi|Customer Support; Another Name=Ade|Sales"
 * Each part: the start of an Ops full name = the name to sign with | its title.
 */

const OPS_PUBLIC_AS_DEFAULT = ['Tom-Blake' => ['Simi', 'Customer Support']];

/** [name start => [name, title]] from OPS_PUBLIC_AS, or the default. */
function ops_public_personas(): array
{
    $raw = trim((string)($_ENV['OPS_PUBLIC_AS'] ?? ''));
    if ($raw === '') {
        return OPS_PUBLIC_AS_DEFAULT;
    }
    $out = [];
    foreach (preg_split('/\s*;\s*/', $raw) ?: [] as $part) {
        if (preg_match('/^(.+?)\s*=\s*([^|]+?)\s*\|\s*(.+)$/u', trim($part), $m)) {
            $out[trim($m[1])] = [trim($m[2]), trim($m[3])];
        }
    }
    return $out;
}

/** [name, title] this Ops person signs client emails with, or null to use their own. */
function ops_public_persona(string $full_name): ?array
{
    $full_name = trim($full_name);
    foreach (ops_public_personas() as $start => $as) {
        if ($start !== '' && mb_stripos($full_name, $start) === 0) {
            return $as;
        }
    }
    return null;
}

/**
 * Who a notice to a client says did something: their own name, or
 * "Customer Support" for someone who signs as another person (a notice
 * about an action names nobody who didn't do it).
 */
function ops_public_actor(?string $full_name): string
{
    $full_name = trim((string)$full_name);
    if ($full_name === '') {
        return 'The Nfelia team';
    }
    $as = ops_public_persona($full_name);
    return $as ? $as[1] : $full_name;
}
