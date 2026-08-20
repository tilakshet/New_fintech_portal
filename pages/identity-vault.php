<?php
$extraScripts = ['/assets/js/pages/identity-vault.js'];
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">Your account, business, and settlement details.</p>
</div>

<div id="identity-vault-loading" class="card text-center py-12">
    <p class="text-md text-text-secondary">Loading...</p>
</div>

<div id="identity-vault-content" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-1 space-y-5">
        <div class="card">
            <h2 class="card-title mb-5">Account access</h2>
            <form id="account-form" novalidate>
                <div class="mb-5">
                    <label for="iv-name" class="field-label">Full name<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="iv-name" name="name" class="field-input" value="<?= e($user['name']) ?>" maxlength="120" required>
                    <p id="iv-name-error" class="field-error hidden"></p>
                </div>
                <div class="mb-6">
                    <label for="iv-email" class="field-label">Email address</label>
                    <input type="email" id="iv-email" class="field-input" value="<?= e($user['email']) ?>" disabled>
                    <p class="field-help">Contact support to change the email associated with your account.</p>
                </div>
                <button type="submit" id="account-submit" class="btn-primary">Save account changes</button>
                <a href="/settings" class="block text-sm text-brand-emphasis mt-3 hover:underline">Change your password &rarr;</a>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title mb-5">Settlement bank</h2>
            <div id="settlement-readonly" class="hidden space-y-3 text-md">
                <div class="flex justify-between gap-3"><span class="text-text-secondary">Holder</span><span id="sb-holder" class="font-semibold text-text-primary text-right"></span></div>
                <div class="flex justify-between gap-3"><span class="text-text-secondary">A/C No.</span><span id="sb-account" class="font-semibold text-brand-emphasis text-right"></span></div>
                <div class="flex justify-between gap-3"><span class="text-text-secondary">IFSC</span><span id="sb-ifsc" class="font-semibold text-text-primary text-right"></span></div>
                <div class="flex justify-between gap-3"><span class="text-text-secondary">Bank</span><span id="sb-bank" class="font-semibold text-text-primary text-right"></span></div>
            </div>
            <p id="settlement-empty" class="hidden text-md text-text-secondary">Add your settlement bank details in the Business &amp; KYC form to the right.</p>
        </div>
    </div>

    <div class="card lg:col-span-2">
        <h2 class="card-title mb-1">Business &amp; KYC information</h2>
        <p class="text-sm text-text-secondary mb-5">Company details and settlement bank, entered once.</p>

        <div id="kyc-locked-banner" class="hidden mb-5 flex items-start gap-2.5 rounded-md bg-info-bg px-4 py-3 text-sm text-info">
            <?= icon('shield', 'w-5 h-5 shrink-0 mt-0.5') ?>
            <span>Business and KYC details are locked. To update these details, please contact your account manager or create a support ticket.</span>
        </div>

        <dl id="kyc-readonly" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-md mb-2">
            <div><dt class="text-text-secondary text-sm mb-0.5">Legal company name</dt><dd id="ro-company-name" class="font-semibold text-text-primary"></dd></div>
            <div><dt class="text-text-secondary text-sm mb-0.5">Company type</dt><dd id="ro-company-type" class="font-semibold text-text-primary"></dd></div>
            <div><dt class="text-text-secondary text-sm mb-0.5">Mobile number</dt><dd id="ro-mobile" class="font-semibold text-text-primary"></dd></div>
            <div><dt class="text-text-secondary text-sm mb-0.5">WhatsApp</dt><dd id="ro-whatsapp" class="font-semibold text-text-primary"></dd></div>
            <div><dt class="text-text-secondary text-sm mb-0.5">PAN number</dt><dd id="ro-pan" class="font-semibold text-text-primary"></dd></div>
            <div><dt class="text-text-secondary text-sm mb-0.5">GSTIN</dt><dd id="ro-gstin" class="font-semibold text-text-primary"></dd></div>
            <div><dt class="text-text-secondary text-sm mb-0.5">Aadhar number</dt><dd id="ro-aadhar" class="font-semibold text-text-primary"></dd></div>
            <div class="sm:col-span-2"><dt class="text-text-secondary text-sm mb-0.5">Office address</dt><dd id="ro-address" class="font-semibold text-text-primary"></dd></div>
        </dl>

        <form id="kyc-form" novalidate>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                <div>
                    <label for="kyc-company-name" class="field-label">Legal company name<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="kyc-company-name" name="legal_company_name" class="field-input" maxlength="160" required>
                </div>
                <div>
                    <label for="kyc-company-type" class="field-label">Company type<span class="text-danger" aria-hidden="true"> *</span></label>
                    <select id="kyc-company-type" name="company_type" class="field-input" required>
                        <option value="">Select...</option>
                        <option value="Private Limited">Private Limited</option>
                        <option value="Public Limited">Public Limited</option>
                        <option value="LLP">LLP</option>
                        <option value="Partnership">Partnership</option>
                        <option value="Sole Proprietorship">Sole Proprietorship</option>
                    </select>
                </div>
                <div>
                    <label for="kyc-mobile" class="field-label">Mobile number<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="tel" id="kyc-mobile" name="mobile_number" class="field-input" maxlength="20" required>
                </div>
                <div>
                    <label for="kyc-whatsapp" class="field-label">WhatsApp number</label>
                    <input type="tel" id="kyc-whatsapp" name="whatsapp_number" class="field-input" maxlength="20">
                </div>
                <div>
                    <label for="kyc-pan" class="field-label">PAN number<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="kyc-pan" name="pan_number" class="field-input uppercase" maxlength="20" required>
                </div>
                <div>
                    <label for="kyc-gstin" class="field-label">GSTIN</label>
                    <input type="text" id="kyc-gstin" name="gstin" class="field-input uppercase" maxlength="20">
                </div>
                <div>
                    <label for="kyc-aadhar" class="field-label">Aadhar number<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="kyc-aadhar" name="aadhar_number" class="field-input" maxlength="20" required>
                </div>
            </div>
            <div class="mb-6">
                <label for="kyc-address" class="field-label">Office address<span class="text-danger" aria-hidden="true"> *</span></label>
                <textarea id="kyc-address" name="office_address" class="field-input" rows="2" maxlength="255" required></textarea>
            </div>

            <h3 class="card-title mb-1 text-lg">Settlement bank</h3>
            <p class="text-sm text-text-secondary mb-4">Where your payouts will be sent. Double-check this carefully — it locks after saving.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
                <div>
                    <label for="kyc-sb-holder" class="field-label">Account holder<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="kyc-sb-holder" name="account_holder" class="field-input" maxlength="120" required>
                </div>
                <div>
                    <label for="kyc-sb-account" class="field-label">Account number<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="kyc-sb-account" name="account_number" class="field-input" maxlength="40" required>
                </div>
                <div>
                    <label for="kyc-sb-ifsc" class="field-label">IFSC code<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="kyc-sb-ifsc" name="ifsc_code" class="field-input uppercase" maxlength="20" required>
                </div>
                <div>
                    <label for="kyc-sb-bank" class="field-label">Bank name<span class="text-danger" aria-hidden="true"> *</span></label>
                    <input type="text" id="kyc-sb-bank" name="bank_name" class="field-input" maxlength="120" required>
                </div>
            </div>

            <p id="kyc-error" class="field-error hidden mb-4"></p>
            <button type="submit" id="kyc-submit" class="btn-primary">Save business &amp; KYC details</button>
        </form>
    </div>
</div>
