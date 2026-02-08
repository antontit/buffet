document.addEventListener('DOMContentLoaded', () => {
  const dishItems = document.querySelectorAll('[data-dish-id]');
  const shelves = document.querySelectorAll('.shelf[data-shelf-id]');
  const message = document.querySelector('.buffet-message');
  const state = {
    draggedPlacementId: null,
    draggedDishEl: null,
  };

  const setMessage = (text) => {
    if (!message) {
      return;
    }
    message.textContent = text;
  };

  const clearShelfState = (shelf) => {
    shelf.classList.remove('is-colliding', 'is-available');
  };

  const setShelfState = (shelf, isColliding) => {
    shelf.classList.toggle('is-colliding', isColliding);
    shelf.classList.toggle('is-available', !isColliding);
  };

  const parseTransferData = (event) => {
    const raw = event.dataTransfer.getData('text/plain');
    if (!raw) {
      return {};
    }
    try {
      return JSON.parse(raw);
    } catch (error) {
      return {};
    }
  };

  const getElementSize = (el) => ({
    width: Number(el.dataset.width || el.getBoundingClientRect().width),
    height: Number(el.dataset.height || el.getBoundingClientRect().height),
  });

  const getDropPoint = (shelf, event, width, height) => {
    const shelfRect = shelf.getBoundingClientRect();
    const dropX = Math.round(event.clientX - shelfRect.left);
    const dropYFromBottom = Math.round(shelfRect.bottom - event.clientY);
    const clampedX = Math.max(0, Math.min(Math.round(dropX), shelfRect.width - width));
    const clampedY = 0;

    return { x: clampedX, y: clampedY, shelfRect };
  };

  const toRect = (x, y, width, height) => ({
    left: x,
    right: x + width,
    bottom: y,
    top: y + height,
  });

  const overlaps = (a, b) => !(
    a.right <= b.left
    || a.left >= b.right
    || a.top <= b.bottom
    || a.bottom >= b.top
  );

  const computeOverlap = (shelf, rect, ignorePlacementId) => Array.from(
    shelf.querySelectorAll('.placed-dish')
  ).some((item) => {
    if (ignorePlacementId && item.dataset.placementId === ignorePlacementId) {
      return false;
    }
      const itemLeft = Number(item.style.left.replace('px', ''));
      const itemBottom = Number(item.style.bottom.replace('px', ''));
    const { width, height } = getElementSize(item);
    const itemRect = toRect(itemLeft, itemBottom, width, height);

    return overlaps(rect, itemRect);
  });

  const isStackCompatible = (targetEl, draggedEl) => (
    targetEl
    && draggedEl
    && targetEl.dataset.isStacked === '1'
    && draggedEl.dataset.isStacked === '1'
    && targetEl.dataset.dishType === draggedEl.dataset.dishType
  );

  const updateCollisionState = (shelf, event) => {
    if (!state.draggedDishEl) {
      clearShelfState(shelf);
      return;
    }

    const targetDishEl = event.target.closest('.placed-dish');
    if (isStackCompatible(targetDishEl, state.draggedDishEl)) {
      setShelfState(shelf, false);
      return;
    }

    const { width, height } = getElementSize(state.draggedDishEl);
    const { x, y } = getDropPoint(shelf, event, width, height);
    const rect = toRect(x, y, width, height);
    const overlap = computeOverlap(shelf, rect, state.draggedPlacementId);

    setShelfState(shelf, overlap);
  };

  const bindDragSource = (item) => {
    item.addEventListener('dragstart', (event) => {
      if (item.dataset.placementId) {
        state.draggedPlacementId = item.dataset.placementId;
        state.draggedDishEl = item;
        event.dataTransfer.setData('text/plain', JSON.stringify({
          placementId: item.dataset.placementId,
        }));
        event.dataTransfer.effectAllowed = 'move';
        return;
      }

      state.draggedDishEl = item;
      event.dataTransfer.setData('text/plain', JSON.stringify({
        dishId: item.dataset.dishId,
      }));
      event.dataTransfer.effectAllowed = 'copy';
    });
    item.addEventListener('dragend', () => {
      state.draggedPlacementId = null;
      state.draggedDishEl = null;
    });
  };

  const createPlacedElement = (sourceDishEl, payload) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'placed-dish';
    wrapper.draggable = true;
    wrapper.dataset.placementId = payload.id;
    wrapper.dataset.dishId = sourceDishEl.dataset.dishId || payload.dishId;
    wrapper.dataset.dishType = sourceDishEl.dataset.dishType;
    wrapper.dataset.isStacked = sourceDishEl.dataset.isStacked;
    wrapper.dataset.image = sourceDishEl.dataset.image || sourceDishEl.src;
    wrapper.dataset.stackId = payload.stackId || '';
    wrapper.dataset.stackIndex = payload.stackIndex || '';
    wrapper.dataset.width = payload.width;
    wrapper.dataset.height = payload.height;
    wrapper.style.left = `${payload.x}px`;
    wrapper.style.bottom = `${payload.y}px`;
    wrapper.style.width = `${payload.width}px`;
    wrapper.style.height = `${payload.height}px`;

    const img = document.createElement('img');
    img.src = wrapper.dataset.image;
    img.alt = sourceDishEl.alt || 'Dish';
    wrapper.appendChild(img);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'placed-delete';
    button.setAttribute('aria-label', 'Delete dish');
    button.setAttribute('draggable', 'false');
    button.textContent = '×';
    wrapper.appendChild(button);

    const badge = document.createElement('div');
    badge.className = 'stack-badge';
    badge.dataset.stackBadge = '1';
    badge.style.display = 'none';
    wrapper.appendChild(badge);

    const controls = document.createElement('div');
    controls.className = 'stack-controls';
    controls.dataset.stackControls = '1';
    controls.style.display = 'none';
    controls.innerHTML = '<button type="button" class="stack-add" aria-label="Add to stack" draggable="false">+</button><button type="button" class="stack-remove" aria-label="Remove from stack" draggable="false">-</button>';
    wrapper.appendChild(controls);

    return wrapper;
  };

  const handleStackDrop = async (shelf, targetDishEl) => {
    const targetPlacementId = targetDishEl.dataset.placementId;

    if (state.draggedPlacementId) {
      if (state.draggedPlacementId === targetPlacementId) {
        return true;
      }

      const response = await fetch('/api/stacks/merge', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          sourcePlacementId: Number(state.draggedPlacementId),
          targetPlacementId: Number(targetPlacementId),
          position: 'top',
        }),
      });

      if (!response.ok) {
        setMessage('Failed to stack dish.');
        return true;
      }

      const payload = await response.json();
      const resolvedStackId = payload.stackId || targetDishEl.dataset.stackId;
      const sourcePlacement = payload.placements.find(
        (p) => String(p.id) === String(state.draggedPlacementId)
      );
      if (sourcePlacement && state.draggedDishEl) {
        state.draggedDishEl.style.left = `${sourcePlacement.x}px`;
        state.draggedDishEl.style.bottom = `${sourcePlacement.y}px`;
      if (sourcePlacement.stackId || resolvedStackId) {
        state.draggedDishEl.dataset.stackId = String(sourcePlacement.stackId || resolvedStackId);
      }
        if (sourcePlacement.stackIndex !== undefined) {
          state.draggedDishEl.dataset.stackIndex = String(sourcePlacement.stackIndex ?? '');
        }
        shelf.appendChild(state.draggedDishEl);
      }

      setMessage('');
      if (payload.placements) {
        applyStackPayload(payload.placements);
      }
      if (resolvedStackId) {
        updateStackUI(String(resolvedStackId));
      }
      return true;
    }

    if (!state.draggedDishEl) {
      return true;
    }

    const dishId = state.draggedDishEl.dataset.dishId;
    const response = await fetch('/api/stacks/add', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        dishId: Number(dishId),
        targetPlacementId: Number(targetPlacementId),
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to stack dish.');
      return true;
    }

    const payload = await response.json();
    const resolvedStackId = payload.stackId || targetDishEl.dataset.stackId;
    setMessage('');
    if (resolvedStackId) {
      targetDishEl.dataset.stackId = String(resolvedStackId);
      targetDishEl.dataset.stackIndex = targetDishEl.dataset.stackIndex || '0';
      updateStackUI(String(resolvedStackId), getStackCount(String(resolvedStackId)) + 1);
    }

    return true;
  };

  const handleMoveDrop = async (shelf, event) => {
    if (!state.draggedPlacementId) {
      return false;
    }

    const placedEl = document.querySelector(`[data-placement-id="${state.draggedPlacementId}"]`);
    if (!placedEl) {
      return true;
    }

    const { width, height } = getElementSize(placedEl);
    const { x, y } = getDropPoint(shelf, event, width, height);
    const stackId = placedEl.dataset.stackId || null;
    const stackCount = stackId ? getStackCount(stackId) : 0;
    if (stackId && stackCount > 1) {
      const response = await fetch(`/api/stacks/${stackId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          shelfId: Number(shelf.dataset.shelfId),
          x,
          y,
        }),
      });

      if (!response.ok) {
        setMessage(response.status === 409 ? 'Collision detected.' : 'Failed to move stack.');
        return true;
      }

      const payload = await response.json();
      payload.placements.forEach((placement) => {
        const itemEl = document.querySelector(`[data-placement-id="${placement.id}"]`);
        if (!itemEl) {
          return;
        }
        itemEl.style.left = `${placement.x}px`;
        itemEl.style.bottom = `${placement.y}px`;
        itemEl.dataset.stackIndex = placement.stackIndex ?? '';
        shelf.appendChild(itemEl);
      });
      setMessage('');
      updateStackUI(stackId);

      return true;
    }

    const response = await fetch(`/api/placements/${state.draggedPlacementId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        shelfId: Number(shelf.dataset.shelfId),
        x,
        y,
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'Collision detected.' : 'Failed to move dish.');
      return true;
    }

    placedEl.style.left = `${x}px`;
    placedEl.style.bottom = `${y}px`;
    shelf.appendChild(placedEl);
    setMessage('');

    return true;
  };

  const handleCreateDrop = async (shelf, event) => {
    const transferData = parseTransferData(event);
    const dishId = transferData.dishId ? String(transferData.dishId) : '';
    if (!dishId) {
      return false;
    }

    const sourceDishEl = state.draggedDishEl
      || document.querySelector(`.buffet-items [data-dish-id="${dishId}"]`);
    if (!sourceDishEl) {
      return true;
    }

    const { width, height } = getElementSize(sourceDishEl);
    const { x } = getDropPoint(shelf, event, width, height);

    const response = await fetch(`/api/shelves/${shelf.dataset.shelfId}/placements`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        shelfId: Number(shelf.dataset.shelfId),
        dishId: Number(dishId),
        x,
      }),
    });

    if (!response.ok) {
      setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to place dish.');
      return true;
    }

    const payload = await response.json();
    const placed = createPlacedElement(sourceDishEl, payload);
    shelf.appendChild(placed);
    bindDragSource(placed);
    setMessage('');
    if (payload.stackId) {
      updateStackUI(payload.stackId);
    }

    return true;
  };

  const handleShelfDrop = async (shelf, event) => {
    const transferData = parseTransferData(event);
    if (transferData.placementId) {
      state.draggedPlacementId = String(transferData.placementId);
    } else if (transferData.dishId && !state.draggedDishEl) {
      state.draggedDishEl = document.querySelector(
        `.buffet-items [data-dish-id="${transferData.dishId}"]`
      );
    }

    const targetDishEl = event.target.closest('.placed-dish');
    if (isStackCompatible(targetDishEl, state.draggedDishEl)) {
      await handleStackDrop(shelf, targetDishEl);
      clearShelfState(shelf);
      return;
    }

    if (await handleMoveDrop(shelf, event)) {
      clearShelfState(shelf);
      return;
    }

    await handleCreateDrop(shelf, event);
    clearShelfState(shelf);
  };

  const handleDeleteClick = async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('placed-delete')) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const placedEl = target.closest('.placed-dish');
    if (!placedEl || !(placedEl instanceof HTMLElement)) {
      return;
    }

    const placementId = placedEl.dataset.placementId;
    if (!placementId) {
      return;
    }
    const stackId = placedEl.dataset.stackId || null;
    const stackCount = stackId ? getStackCount(stackId) : 0;

    try {
      const response = await fetch(
        stackId && stackCount > 1
          ? `/api/stacks/${stackId}`
          : `/api/placements/${placementId}`,
        {
          method: 'DELETE',
        }
      );

      if (!response.ok) {
        setMessage('Failed to delete dish.');
        return;
      }

      if (response.status === 204) {
        if (stackId && stackCount > 1) {
          document
            .querySelectorAll(`[data-stack-id="${stackId}"]`)
            .forEach((el) => el.remove());
        } else {
          placedEl.remove();
        }
        setMessage('');
      }
    } catch (error) {
      setMessage('Failed to delete dish.');
    }
  };

  dishItems.forEach((item) => {
    bindDragSource(item);
  });

  shelves.forEach((shelf) => {
    shelf.addEventListener('dragover', (event) => {
      event.preventDefault();
      event.dataTransfer.dropEffect = state.draggedPlacementId ? 'move' : 'copy';
      updateCollisionState(shelf, event);
    });

    shelf.addEventListener('dragleave', (event) => {
      if (!shelf.contains(event.relatedTarget)) {
        clearShelfState(shelf);
      }
    });

    shelf.addEventListener('drop', async (event) => {
      event.preventDefault();
      await handleShelfDrop(shelf, event);
    });
  });

  const getStackCount = (stackId) => {
    const stackKey = String(stackId);
    const elements = Array.from(document.querySelectorAll(`[data-stack-id="${stackKey}"]`));
    if (elements.length === 0) {
      return 0;
    }
    const stored = Number(elements[0].dataset.stackCount || 0);
    return stored > 0 ? stored : elements.length;
  };

  const updateStackUI = (stackId, countOverride = null) => {
    if (!stackId) {
      return;
    }
    const stackKey = String(stackId);
    const elements = Array.from(document.querySelectorAll(`[data-stack-id="${stackKey}"]`));
    if (elements.length === 0) {
      return;
    }

    const stored = Number(elements[0].dataset.stackCount || 0);
    const count = countOverride !== null ? countOverride : (stored > 0 ? stored : elements.length);
    elements.forEach((el) => {
      const badge = el.querySelector('[data-stack-badge]');
      const controls = el.querySelector('[data-stack-controls]');
      el.dataset.stackCount = String(count);
      if (badge) {
        badge.textContent = String(count);
        badge.style.display = count > 1 ? 'flex' : 'none';
      }
      if (controls) {
        controls.style.display = 'none';
      }
    });

    const topEl = elements.reduce((current, candidate) => {
      const currentIndex = Number(current.dataset.stackIndex || 0);
      const candidateIndex = Number(candidate.dataset.stackIndex || 0);
      return candidateIndex >= currentIndex ? candidate : current;
    }, elements[0]);

    const topBadge = topEl.querySelector('[data-stack-badge]');
    const topControls = topEl.querySelector('[data-stack-controls]');
    if (topBadge) {
      topBadge.style.display = count > 1 ? 'flex' : 'none';
    }
    if (topControls && count > 1) {
      topControls.style.display = 'flex';
    }
  };

  const applyStackPayload = (placements) => {
    const updatedStackIds = new Set();
    placements.forEach((placement) => {
      const el = document.querySelector(`[data-placement-id="${placement.id}"]`);
      if (!el) {
        return;
      }
      if (placement.stackId) {
        el.dataset.stackId = String(placement.stackId);
        updatedStackIds.add(String(placement.stackId));
      } else {
        el.dataset.stackId = '';
      }
      el.dataset.stackIndex = placement.stackIndex ?? '';
    });
    updatedStackIds.forEach((stackId) => updateStackUI(stackId));
  };

  const handleStackControls = async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (!target.classList.contains('stack-add') && !target.classList.contains('stack-remove')) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const placedEl = target.closest('.placed-dish');
    if (!placedEl) {
      return;
    }

    const placementId = placedEl.dataset.placementId;
    const dishId = placedEl.dataset.dishId;
    const stackId = placedEl.dataset.stackId || null;
    if (!placementId) {
      return;
    }

    if (target.classList.contains('stack-add')) {
      const response = await fetch('/api/stacks/add', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          dishId: Number(dishId),
          targetPlacementId: Number(placementId),
        }),
      });

      if (!response.ok) {
        setMessage(response.status === 409 ? 'No space available on this shelf.' : 'Failed to stack dish.');
        return;
      }

      const payload = await response.json();
      const resolvedStackId = payload.stackId || placedEl.dataset.stackId;
      if (resolvedStackId) {
        placedEl.dataset.stackId = String(resolvedStackId);
        placedEl.dataset.stackIndex = placedEl.dataset.stackIndex || '0';
        updateStackUI(String(resolvedStackId), getStackCount(String(resolvedStackId)) + 1);
      }
      setMessage('');
      return;
    }

    if (target.classList.contains('stack-remove')) {
      if (!stackId) {
        setMessage('Stack not found.');
        return;
      }

      const response = await fetch('/api/stacks/unstack', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          stackId: Number(stackId),
        }),
      });

      if (!response.ok) {
        setMessage('Failed to remove from stack.');
        return;
      }

      const payload = await response.json();
      const stackKey = String(payload.stackId);
      const currentCount = getStackCount(stackKey);
      if (payload.removedId) {
        const removedEl = document.querySelector(`[data-placement-id="${payload.removedId}"]`);
        if (removedEl) {
          removedEl.remove();
        }
      }

      updateStackUI(stackKey, Math.max(0, currentCount - 1));
      setMessage('');
    }
  };

  document.addEventListener('click', handleDeleteClick);
  document.addEventListener('click', handleStackControls);
});
