(function(){
  // Evita doble init
  if (window.__AM_CONV_INIT__) return;
  window.__AM_CONV_INIT__ = true;

  // Fallbacks if AM_REST/AM_NONCE not defined globally
  const AM_REST = (window.AM_REST || '/wp-json/') + '';
  const AM_NONCE = (window.AM_NONCE || '') + '';
  const deletingCids = new Set();
  function removeEmptyGroup(list) {
    if (!list || !list.classList?.contains('am-chat-list')) return;
    if (list.children.length === 0) {
      const header = list.previousElementSibling;
      if (header && header.tagName.toLowerCase() === 'h5') header.remove();
      list.remove();
    }
  }

  function moveChatItemToDateGroup(item, newDateKey) {
    const oldList = item.parentElement;
    const container = item.closest('.am-assistant-chats-container');
    if (!container) return;
    let groupHeader = Array.from(container.querySelectorAll('h5')).find(h => h.textContent === newDateKey);
    let groupList;
    if (!groupHeader) {
      groupHeader = document.createElement('h5');
      groupHeader.textContent = newDateKey;
      groupList = document.createElement('ul');
      groupList.className = 'am-chat-list';
      container.insertBefore(groupHeader, container.querySelector('h5'));
      container.insertBefore(groupList, groupHeader.nextSibling);
    } else {
      groupList = groupHeader.nextElementSibling;
    }
    groupList.appendChild(item);
    removeEmptyGroup(oldList);
  }

  function getTodayLabel() {
    return 'Today';
  }

  function getDateLabel(dateStr) {
    // Use same logic as PHP am_format_date_group
    const today = new Date();
    const d = new Date(dateStr);
    const diffDays = Math.floor((today - d) / 86400000);
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays <= 7) return diffDays + ' days ago';
    return d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function initContainer(cont) {
    if (!cont || cont.__amBound) return;
    cont.__amBound = true;

    // --- Pinned agents helpers ---
    const PIN_ICON = '<img src="https://wa4u.ai/wp-content/uploads/2025/09/pin.svg" alt="pin">';
    function getPins() {
      try { return JSON.parse(localStorage.getItem('amPinnedAgents') || '[]'); }
      catch (_) { return []; }
    }
    function savePins(arr) { localStorage.setItem('amPinnedAgents', JSON.stringify(arr)); }
    function restorePins() {
      const list = cont.querySelector('.am-agent-list');
      if (!list) return;
      const pins = getPins();
      pins.forEach(p => {
        const existing = list.querySelector(`.am-agent-item[data-agent-id="${p.id}"]`);
        if (existing) {
          existing.classList.add('pinned');
          const btn = existing.querySelector('.am-pin-btn');
          if (btn) btn.innerHTML = PIN_ICON + 'Unpin';
          return;
        }
        const li = document.createElement('li');
        li.className = 'am-agent-item pinned';
        li.dataset.agentId = p.id;
        li.dataset.agentName = p.name;
        li.dataset.avatarUrl = p.avatar || '';
        li.dataset.chatUrl = p.chatUrl || '';
        if (p.avatar) {
          const img = document.createElement('img');
          img.className = 'am-agent-avatar';
          img.src = p.avatar;
          img.alt = p.name;
          li.appendChild(img);
        }
        const span = document.createElement('span');
        span.className = 'am-agent-name';
        span.textContent = p.name;
        li.appendChild(span);
        const menuCont = document.createElement('div');
        menuCont.className = 'am-agent-menu-container';
        menuCont.innerHTML = `<button type="button" class="am-agent-menu-btn" aria-label="Open menu"><svg width="12" height="4" viewBox="0 0 12 4" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="1.92051" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/><ellipse cx="5.99082" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/><ellipse cx="10.0572" cy="1.70045" rx="1.52597" ry="1.52076" fill="#3A354E"/></svg></button><div class="am-agent-menu"><button type="button" class="am-new-chat-btn" aria-label="New chat">New Chat</button><button type="button" class="am-pin-btn" aria-label="Unpin">${PIN_ICON}Unpin</button></div>`;
        li.appendChild(menuCont);
        list.insertBefore(li, list.firstChild);
      });
    }
    restorePins();

    function bindAgentMenus() {
      cont.querySelectorAll('.am-agent-menu-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const menu = btn.nextElementSibling;
          if (menu) {
            cont.querySelectorAll('.am-chat-menu.open, .am-agent-menu.open').forEach(m => {
              if (m !== menu) m.classList.remove('open');
            });
            menu.classList.toggle('open');
          }
        });
      });
    }
    bindAgentMenus();

    if (!cont.__docListener) {
      cont.__docListener = (e) => {
        if (!cont.contains(e.target)) {
          cont.querySelectorAll('.am-chat-menu.open, .am-agent-menu.open').forEach(m => m.classList.remove('open'));
        }
      };
      document.addEventListener('click', cont.__docListener);
    }

    // --- Search filter ---
    const searchInput = cont.querySelector('.am-search-input');
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase();
        cont.querySelectorAll('.am-agent-item').forEach(li => {
          const name = li.querySelector('.am-agent-name')?.textContent.toLowerCase() || '';
          li.style.display = name.includes(q) ? '' : 'none';
        });
        cont.querySelectorAll('h5').forEach(h => {
          const list = h.nextElementSibling;
          if (list && list.classList.contains('am-chat-list')) {
            let any = false;
            list.querySelectorAll('.am-chat-item').forEach(li => {
              const name = li.querySelector('.am-chat-name')?.textContent.toLowerCase() || '';
              const match = name.includes(q);
              li.style.display = match ? '' : 'none';
              if (match) any = true;
            });
            h.style.display = any ? '' : 'none';
            list.style.display = any ? '' : 'none';
          }
        });
      });
    }

    // Listen for conversation updates
    window.addEventListener('am:conversation-updated', (e) => {
      const { cid, title, agentId, avatarUrl } = e.detail;
      const item = cont.querySelector(`.am-chat-item[data-conv-uid="${cid}"]`);
      
      if (item) {
        const todayLabel = getTodayLabel(); // retorna 'Today'
        const header = Array.from(cont.querySelectorAll('h5'))
          .find(h => h.textContent.trim().toLowerCase() === todayLabel.toLowerCase());
        if (!header) {
          // si no existe sección "Today", créala al vuelo
          ensureTodaySection(cont);
        }
        updateConversationTimestamp(item);
      } else {
        // Create new conversation item if doesn't exist
        const newItem = createConversationItem({
          public_id: cid,
          agent_id: agentId,
          title: title || 'New conversation',
          avatar_url: avatarUrl
        });
        
        // Add to Today section
        const todayList = ensureTodaySection(cont);
        todayList.insertBefore(newItem, todayList.firstChild);
      }
    });

    cont.addEventListener('click', async (e)=>{
      // Conversation menu toggle
      const menuBtn = e.target.closest('.am-chat-menu-btn');
      if (menuBtn) {
        e.stopPropagation();
        const menu = menuBtn.nextElementSibling;
        if (menu) {
          cont.querySelectorAll('.am-chat-menu.open, .am-agent-menu.open').forEach(m => {
            if (m !== menu) m.classList.remove('open');
          });
          menu.classList.toggle('open');
        }
        return;
      }

      // Close menus when clicking outside
      if (!e.target.closest('.am-chat-menu') && !e.target.closest('.am-agent-menu')) {
        cont.querySelectorAll('.am-chat-menu.open, .am-agent-menu.open').forEach(m => m.classList.remove('open'));
      }

      // New Chat from agent menu
      const newChatBtn = e.target.closest('.am-new-chat-btn');
      if (newChatBtn) {
        e.stopPropagation();
        const item = newChatBtn.closest('.am-agent-item');
        const url = item?.dataset?.chatUrl;
        newChatBtn.closest('.am-agent-menu')?.classList.remove('open');
        if (url) window.location.href = url;
        return;
      }

      // Pin toggle
      const pinBtn = e.target.closest('.am-pin-btn');
      if (pinBtn) {
        e.stopPropagation();
        const item = pinBtn.closest('.am-agent-item');
        const id = item?.dataset?.agentId;
        if (!id) return;
        const pins = getPins();
        const exists = pins.find(p => String(p.id) === String(id));
        if (exists) {
          const updated = pins.filter(p => String(p.id) !== String(id));
          savePins(updated);
          item.classList.remove('pinned');
        } else {
          const data = {
            id,
            name: item.dataset.agentName || '',
            avatar: item.dataset.avatarUrl || '',
            chatUrl: item.dataset.chatUrl || ''
          };
          pins.push(data);
          savePins(pins);
          item.classList.add('pinned');
          const list = item.parentElement;
          if (list) list.insertBefore(item, list.firstChild);
        }
        pinBtn.innerHTML = PIN_ICON + (item.classList.contains('pinned') ? 'Unpin' : 'Pin');
        pinBtn.closest('.am-agent-menu')?.classList.remove('open');
        return;
      }

      // Rename chat
      const renameBtn = e.target.closest('.am-rename-btn');
      if (renameBtn) {
        e.stopPropagation();
        const item = renameBtn.closest('.am-chat-item');
        const cid = item?.dataset?.convUid;
        if (!cid) return;

        // Close menu after clicking
        renameBtn.closest('.am-chat-menu')?.classList.remove('open');

        const titleEl = item.querySelector('.am-chat-name a');
        const currentTitle = titleEl ? titleEl.textContent.trim() : '';
        const newTitle = prompt('New title:', currentTitle || 'Chat');
        if (!newTitle || newTitle.trim() === '') return;

        try {
          const r = await fetch(window.AM_REST + 'am/v1/rename_conversation', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': window.AM_NONCE
            },
            body: JSON.stringify({
              conversation_uid: cid,
              title: newTitle.trim()
            })
          });
          if (!r.ok) throw new Error('API error');
          if (titleEl) titleEl.textContent = newTitle.trim();

          // Move item to "Today" group (simulate updated_at change)
          const item = renameBtn.closest('.am-chat-item');
          const todayLabel = getTodayLabel();
          moveChatItemToDateGroup(item, todayLabel);
        } catch (err) {
          alert('Error renaming chat. Please try again.');
        }
        return;
      }

      // Delete chat  
      const deleteBtn = e.target.closest('.am-delete-btn');
      if (deleteBtn) {
        e.stopPropagation();
        const item = deleteBtn.closest('.am-chat-item');
        const cid = item?.dataset?.convUid;
        if (!cid || deletingCids.has(cid)) return;

        // Close menu after clicking
        deleteBtn.closest('.am-chat-menu')?.classList.remove('open');
        
        // Enhanced delete handling
        async function handleDelete(item, cid) {
          if (!confirm('Are you sure you want to delete this chat?')) return;

          try {
            const r = await fetch(window.AM_REST + 'am/v1/delete_conversation', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.AM_NONCE
              },
              body: JSON.stringify({ conversation_uid: cid })
            });

            if (!r.ok) throw new Error('Delete failed');

            // Remove from list
            const parentList = item.parentElement;
            item.remove();
            removeEmptyGroup(parentList);

            // Check if viewing deleted conversation
            const currentCid = new URL(window.location.href).searchParams.get('cid');
            if (currentCid === cid) {
              // Update URL and redirect
              const url = new URL(window.location.href);
              url.searchParams.delete('cid');
              window.history.replaceState({}, '', url.toString());
              window.location.href = url.toString();
            }

          } catch (err) {
            console.error('Delete error:', err);
            alert('Error deleting conversation');
          }
        }

        if (!confirm('Are you sure you want to delete this chat?')) return;
        deletingCids.add(cid);

        deleteBtn.disabled = true;
        const prevText = deleteBtn.textContent;
        deleteBtn.textContent = 'Deleting...';

        try {
          const r = await fetch(window.AM_REST + 'am/v1/delete_conversation', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': window.AM_NONCE
            },
            body: JSON.stringify({
              conversation_uid: cid
            })
          });
          if (!r.ok) throw new Error('API error');
          
          // Remove from DOM
          const parentList2 = item.parentElement;
          item.remove();
          removeEmptyGroup(parentList2);

          // Check if viewing deleted conversation
          const currentCid = new URL(window.location.href).searchParams.get('cid');
          if (currentCid === cid) {
            // Update URL
            const url = new URL(window.location.href);
            url.searchParams.delete('cid');
            window.history.replaceState({}, '', url.toString());
            
            // Clear chat view
            const chatContainer = document.querySelector('.openai-chat-container');
            if (chatContainer) {
              chatContainer.innerHTML = '<div class="error">This conversation has been deleted.</div>';
            }

            // Redirect after short delay
            //setTimeout(() => {
            //  window.location.href = url.toString();
            //}, 2000);
            window.location.replace(url.toString());
          }
        } catch (err) {
          console.error('Delete error:', err);
          alert('Error deleting chat. Please try again.');
          deleteBtn.disabled = false;
          deleteBtn.textContent = prevText;
        } finally {
          deletingCids.delete(cid);
        }
      }
    });

    // Add this function inside init scope
    function updateConversationTimestamp(item) {
      const oldList = item.parentElement;
      const todayHeader = Array.from(cont.querySelectorAll('h5')).find(h => h.textContent === 'Today');
      const todayList = todayHeader?.nextElementSibling;
      
      if (!todayHeader) {
        // Create Today section if doesn't exist
        const newHeader = document.createElement('h5');
        newHeader.textContent = 'Today';
        const newList = document.createElement('ul');
        newList.className = 'am-chat-list';
        
        // Insert at top
        const firstHeader = cont.querySelector('h5');
        if (firstHeader) {
          cont.insertBefore(newHeader, firstHeader);
          cont.insertBefore(newList, firstHeader);
          newList.appendChild(item);
        }
      } else if (todayList) {
        todayList.insertBefore(item, todayList.firstChild);
      }
      removeEmptyGroup(oldList);
    }

    // Helper to ensure Today section exists
    function ensureTodaySection(container) {
      let todayHeader = Array.from(container.querySelectorAll('h5'))
        .find(h => h.textContent === 'Today');
      
      if (!todayHeader) {
        todayHeader = document.createElement('h5');
        todayHeader.textContent = 'Today';
        const list = document.createElement('ul');
        list.className = 'am-chat-list';
        
        container.insertBefore(todayHeader, container.firstChild);
        container.insertBefore(list, todayHeader.nextSibling);
        return list;
      }
      
      return todayHeader.nextElementSibling;
    }

    // Listen for conversation updates
    window.addEventListener('am:conversation-updated', (e) => {
      const { cid } = e.detail;
      const item = document.querySelector(`.am-chat-item[data-conv-uid="${cid}"]`);
      if (item) updateConversationTimestamp(item);
    });
  }

  // Initial binding
  document.querySelectorAll('.am-assistant-chats-container').forEach(initContainer);

  // Watch for dynamic containers
  const mo = new MutationObserver((muts)=>{
    muts.forEach(m=>{
      m.addedNodes?.forEach(n=>{
        if (n.nodeType !== 1) return;
        if (n.classList?.contains('am-assistant-chats-container')) initContainer(n);
        n.querySelectorAll?.('.am-assistant-chats-container').forEach(initContainer);
      });
    });
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
})();