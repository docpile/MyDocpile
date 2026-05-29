<?php
/**
 * ============================================================================
 * MODULE: Cryptographic JavaScript Engine
 * ============================================================================
 * Implements the client-side WebCrypto API logic for end-to-end encryption (E2EE). 
 * Handles PBKDF2 key derivation, AES-GCM encryption/decryption, and key wrapping.
 * NOTE: Executed exclusively by the client browser.
 * 
 * THIS IS PART OF THE PSEUDO "JS FILE" - SO THE NO PHP CODE IN THIS FILE!
 */
?><script>
// ============================================================================
// E2E WEBCRYPTO ENGINE (AES-256-GCM) with KEK/DEK Envelope Architecture (V2 Only)
// ============================================================================
const myCloudCrypto = (function() {
    // Ephemeral Master Key (Session-bound, never exported)
    let sessionMasterKey = null;
    // Encrypted directory keys mapped by RAM wrapper: { "/secure_dir": { iv, wrappedKey } }
    let wrappedDirKeys = {}; 

    // Safe Binary to Base64 (Bypasses ES6 spread stack limits & encoding errors)
    function buf2b64(buf) {
        const bytes = new Uint8Array(buf);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    // Safe Base64 to Binary Array
    function b642buf(b64) {
        const raw = atob(b64);
        const bytes = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) {
            bytes[i] = raw.charCodeAt(i);
        }
        return bytes;
    }

    // Restore session keys synchronously to prevent UI flashes on reload
    const savedKeys = sessionStorage.getItem('myCloud_WrappedKeys');
    if (savedKeys) {
        try {
            const parsed = JSON.parse(savedKeys);
            for (const k in parsed) {
                wrappedDirKeys[k] = {
                    iv: b642buf(parsed[k].iv),
                    wrapped: b642buf(parsed[k].wrapped).buffer
                };
            }
        } catch(e) {}
    }

    // Generate a temporary session master key to encrypt the directory keys in memory
    async function initSessionMasterKey() {
        if (sessionMasterKey) return;
        const stored = sessionStorage.getItem('myCloud_MasterKey');
        if (stored) {
            const raw = b642buf(stored);
            sessionMasterKey = await crypto.subtle.importKey("raw", raw, "AES-GCM", false, ["wrapKey", "unwrapKey"]);
            return;
        }
        const raw = crypto.getRandomValues(new Uint8Array(32));
        sessionStorage.setItem('myCloud_MasterKey', buf2b64(raw));
        sessionMasterKey = await crypto.subtle.importKey("raw", raw, "AES-GCM", false, ["wrapKey", "unwrapKey"]);
    }

    // Modern V2 Post-Quantum Safe KEK Derivation (SHA-512, 600k Iterations)
    async function deriveKekFromPassword(password, salt) {
        const enc = new TextEncoder();
        const keyMaterial = await crypto.subtle.importKey(
            "raw", enc.encode(password), { name: "PBKDF2" }, false, ["deriveKey"]
        );
        return await crypto.subtle.deriveKey(
            { name: "PBKDF2", salt: salt, iterations: 600000, hash: "SHA-512" },
            keyMaterial, { name: "AES-GCM", length: 256 }, true, ["wrapKey", "unwrapKey"]
        );
    }

    function updateSessionStorage() {
        const toSave = {};
        for (const k in wrappedDirKeys) {
            toSave[k] = {
                iv: buf2b64(wrappedDirKeys[k].iv),
                wrapped: buf2b64(wrappedDirKeys[k].wrapped)
            };
        }
        sessionStorage.setItem('myCloud_WrappedKeys', JSON.stringify(toSave));
    }

    function getCryptoRoot(path) {
        if (!path) return null;
        let walker = path.replace(/\/$/, '');
        while (walker !== '' && walker !== '/') {
            if (wrappedDirKeys[walker] || (typeof myCloudState !== 'undefined' && myCloudState.encryptedDirs && myCloudState.encryptedDirs.has(walker))) return walker;
            walker = walker.substring(0, walker.lastIndexOf('/'));
        }
        if (wrappedDirKeys['/'] || (typeof myCloudState !== 'undefined' && myCloudState.encryptedDirs && myCloudState.encryptedDirs.has('/'))) return '/';
        return null;
    }

    return {
        isDirEncrypted: function(dirPath) { return getCryptoRoot(dirPath) !== null; },
        getCryptoRoot: getCryptoRoot,

        // Setup or Unlock a directory using KEK/DEK envelope
        unlockDirectory: async function(dirPath, password, serverPayload = null) {
            await initSessionMasterKey();
            let rawDirKey; // The actual DEK
            let isNewVault = (serverPayload === null);

            if (isNewVault) {
                // 1. Create brand new V2 vault
                rawDirKey = await crypto.subtle.generateKey({name: "AES-GCM", length: 256}, true, ["encrypt", "decrypt"]);
                const salt = crypto.getRandomValues(new Uint8Array(32));
                const kek = await deriveKekFromPassword(password, salt);
                
                const iv = crypto.getRandomValues(new Uint8Array(12));
                const wrappedDEK = await crypto.subtle.wrapKey("raw", rawDirKey, kek, {name: "AES-GCM", iv: iv});

                const exportPayload = JSON.stringify({
                    version: 2,
                    salt: buf2b64(salt),
                    iv: buf2b64(iv),
                    wrappedDEK: buf2b64(wrappedDEK)
                });

                // Wrap for session RAM
                const sessionIv = crypto.getRandomValues(new Uint8Array(12));
                const sessionWrapped = await crypto.subtle.wrapKey("raw", rawDirKey, sessionMasterKey, { name: "AES-GCM", iv: sessionIv });
                wrappedDirKeys[dirPath] = { iv: sessionIv, wrapped: sessionWrapped };
                updateSessionStorage();

                // Base64 encode the entire JSON payload to safely traverse WAFs and POST sanitizers
                return { payload: btoa(exportPayload) };
            } else {
                // 2. Unlock existing vault (V2 only)
                let data;
                try { 
                    // Attempt to decode the Base64 wrapper (V2)
                    let decoded = serverPayload;
                    try { decoded = atob(serverPayload); } catch(e) {}

                    data = JSON.parse(decoded); 
                    if (!data.version || data.version < 2) throw new Error("Outdated vault version");
                } catch(e) {
                    throw new Error("Invalid or legacy vault payload. V1 vaults are no longer supported.");
                }

                const salt = b642buf(data.salt);
                const iv = b642buf(data.iv);
                const wrappedDEK = b642buf(data.wrappedDEK);
                
                const kek = await deriveKekFromPassword(password, salt);
                try {
                    rawDirKey = await crypto.subtle.unwrapKey("raw", wrappedDEK, kek, {name: "AES-GCM", iv: iv}, {name: "AES-GCM", length: 256}, true, ["encrypt", "decrypt"]);
                } catch(e) { throw new Error("Incorrect Password"); }

                // Wrap for session RAM
                const sessionIv = crypto.getRandomValues(new Uint8Array(12));
                const sessionWrapped = await crypto.subtle.wrapKey("raw", rawDirKey, sessionMasterKey, { name: "AES-GCM", iv: sessionIv });
                wrappedDirKeys[dirPath] = { iv: sessionIv, wrapped: sessionWrapped };
                updateSessionStorage();
                
                return { unlocked: true };
            }
        },

        // Fast password rotation (Wraps existing DEK with a new KEK)
        changeVaultPassword: async function(dirPath, newPassword) {
            const dek = await this.getDirKey(dirPath);
            if (!dek) throw new Error("Vault is currently locked. Unlock it first to change the password.");

            const newSalt = crypto.getRandomValues(new Uint8Array(32));
            const newKek = await deriveKekFromPassword(newPassword, newSalt);
            const newIv = crypto.getRandomValues(new Uint8Array(12));

            const newWrappedDEK = await crypto.subtle.wrapKey("raw", dek, newKek, {name: "AES-GCM", iv: newIv});

            const payload = JSON.stringify({
                version: 2,
                salt: buf2b64(newSalt),
                iv: buf2b64(newIv),
                wrappedDEK: buf2b64(newWrappedDEK)
            });

            const fd = new URLSearchParams({ 
                myCloud_action: 'crypto_change_pwd', 
                myCloud_key: myCloudState.key, 
                myCloud_token: typeof myCloudCsrfToken !== 'undefined' ? myCloudCsrfToken : '', 
                path: dirPath, 
                payload: btoa(payload) // Safe Base64 wrap
            });
            const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status !== 'OK') throw new Error(res.msg || "Failed to sync new keys with server.");
        },

        // Get the active directory key (Unwraps it into memory only for the duration of the operation)
        getDirKey: async function(dirPath) {
            await initSessionMasterKey();
            const root = getCryptoRoot(dirPath);
            if (!root || !wrappedDirKeys[root]) return null;
            const item = wrappedDirKeys[root];
            return await crypto.subtle.unwrapKey(
                "raw", item.wrapped, sessionMasterKey, 
                { name: "AES-GCM", iv: item.iv }, { name: "AES-GCM", length: 256 }, 
                true, ["encrypt", "decrypt", "wrapKey"]
            );
        },

        isDirUnlocked: function(dirPath) {
            const root = getCryptoRoot(dirPath);
            return root ? !!wrappedDirKeys[root] : false;
        },
        
        lockDirectory: function(dirPath) {
            const root = getCryptoRoot(dirPath);
            if (root && wrappedDirKeys[root]) {
                delete wrappedDirKeys[root];
                updateSessionStorage();
                return true;
            }
            return false;
        },

        // Encrypt File Content (Compatible with Bash standard)
        // Format: [Placeholder 16B Salt Space] [IV 12B] [Ciphertext + AuthTag 16B]
        encryptFile: async function(dirPath, fileBlob) {
            const key = await this.getDirKey(dirPath);
            if (!key) throw new Error("Directory locked.");
            
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const buffer = await fileBlob.arrayBuffer();
            const encryptedBuffer = await crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, key, buffer);
            
            const emptySalt = new Uint8Array(16);
            const finalBlob = new Blob([emptySalt, iv, encryptedBuffer], { type: "application/octet-stream" });
            return finalBlob;
        },

        // Decrypt File Content
        decryptFile: async function(dirPath, encryptedBlob) {
            const key = await this.getDirKey(dirPath);
            if (!key) throw new Error("Directory locked.");

            const buffer = await encryptedBlob.arrayBuffer();

            // Guard against 0-byte or corrupted tiny files
            if (buffer.byteLength === 0) return new Blob([]);
            if (buffer.byteLength < 44) {
                // Minimum size: 16B Pad + 12B IV + 16B AuthTag = 44 bytes
                throw new Error("File is corrupted or not properly encrypted (too small).");
            }

            const iv = buffer.slice(16, 28);
            const ciphertext = buffer.slice(28);

            const decryptedBuffer = await crypto.subtle.decrypt({ name: "AES-GCM", iv: new Uint8Array(iv) }, key, ciphertext);
            return new Blob([decryptedBuffer]);
        },

        // Encrypt Filename (Base64Url encoded)
        encryptName: async function(dirPath, clearName) {
            const key = await this.getDirKey(dirPath);
            if (!key) return clearName;
            
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const enc = new TextEncoder();
            const encrypted = await crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, key, enc.encode(clearName));
            
            const combined = new Uint8Array(12 + encrypted.byteLength);
            combined.set(iv, 0);
            combined.set(new Uint8Array(encrypted), 12);
            
            return buf2b64(combined).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '') + '.enc';
        },

        // Decrypt Filename
        decryptName: async function(dirPath, encName) {
            if (!encName.endsWith('.enc')) return encName;
            const key = await this.getDirKey(dirPath);
            if (!key) return encName; // Return raw if locked

            try {
                let b64 = encName.replace(/\.enc$/, '').replace(/-/g, '+').replace(/_/g, '/');
                
                // Restore Base64 padding to satisfy strict browsers
                const padLen = 4 - (b64.length % 4);
                if (padLen < 4) b64 += '='.repeat(padLen);
                
                const combined = b642buf(b64);
                const iv = combined.slice(0, 12);
                const ciphertext = combined.slice(12);
                
                const decrypted = await crypto.subtle.decrypt({ name: "AES-GCM", iv: iv }, key, ciphertext);
                return new TextDecoder().decode(decrypted);
            } catch (e) {
                return encName; // Decryption failed (wrong key/corrupted)
            }
        }
    };
})();
</script>