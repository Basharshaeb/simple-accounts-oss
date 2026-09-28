<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">دليل الحسابات</h3>
        <p class="text-muted small m-0">إضافة وتعديل واستعراض الشجرة المحاسبية المنظمة للشركة</p>
      </div>

      <button class="btn btn-primary" @click="openCreateModal(null)">
        <i class="bi bi-plus-lg me-1"></i> إضافة حساب رئيسي جديد
      </button>
    </div>

    <!-- Tree Card -->
    <div class="card p-4 bg-white">
      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-primary" role="status"></div>
      </div>

      <div v-else-if="tree.length === 0" class="text-center text-muted py-5">
        لا توجد حسابات مضافة لهذه الشركة بعد.
      </div>

      <div v-else class="tree-root">
        <div v-for="node in tree" :key="node.id" class="mb-3">
          <AccountNode :node="node" @add-child="openCreateModal" @edit-account="openEditModal" @delete-account="deleteAccount" />
        </div>
      </div>
    </div>

    <!-- Modal to Add or Edit Account -->
    <div class="modal fade" id="accountModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-light">
            <h5 class="modal-title fw-bold">
              <span v-if="isEditing"><i class="bi bi-pencil-square text-primary me-2"></i>تعديل الحساب ({{ form.code }} - {{ form.name }})</span>
              <span v-else-if="parentAccount"><i class="bi bi-plus-circle text-success me-2"></i>إضافة حساب فرعي تحت ({{ parentAccount.code }} - {{ parentAccount.name }})</span>
              <span v-else><i class="bi bi-plus-circle text-primary me-2"></i>إضافة حساب رئيسي جديد</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="saveAccount">
            <div class="modal-body">
              <div v-if="modalError" class="alert alert-danger py-2 small mb-3">
                {{ modalError }}
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">رقم / كود الحساب</label>
                <input v-model="form.code" type="text" class="form-control" placeholder="مثال: 1111" required />
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">اسم الحساب</label>
                <input v-model="form.name" type="text" class="form-control" placeholder="اسم الحساب المحاسبي" required />
              </div>

              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label font-weight-bold">نوع الحساب</label>
                  <select v-model="form.type" class="form-select" :disabled="!!parentAccount && !isEditing" required>
                    <option value="ASSET">أصول</option>
                    <option value="LIABILITY">خصوم</option>
                    <option value="EQUITY">حقوق ملكية</option>
                    <option value="REVENUE">إيرادات</option>
                    <option value="EXPENSE">مصروفات</option>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label font-weight-bold">طبيعة الحساب</label>
                  <select v-model="form.nature" class="form-select" :disabled="!!parentAccount && !isEditing" required>
                    <option value="DEBIT">مدين</option>
                    <option value="CREDIT">دائن</option>
                  </select>
                </div>
              </div>

              <!-- Multi Currency Checkboxes -->
              <div class="mb-3 bg-light p-3 rounded">
                <label class="form-label font-weight-bold d-block mb-2">العملات المسموح بها للحساب</label>
                <div class="d-flex flex-wrap gap-3">
                  <div v-for="curr in currencies" :key="curr.id" class="form-check">
                    <input class="form-check-input" type="checkbox" :value="curr.id" v-model="form.currency_ids" :id="`curr-${curr.id}`" />
                    <label class="form-check-label fw-bold" :for="`curr-${curr.id}`">
                      {{ curr.code }} ({{ curr.symbol }})
                      <span v-if="curr.exchange_rate == 1 || curr.code === 'SAR'" class="badge bg-primary-subtle text-primary ms-1">عملة أساسية <span class="text-danger">*</span></span>
                    </label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">تصنيف الحساب</label>
                <div class="d-flex gap-4">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" :value="false" v-model="form.is_group" id="isSub" />
                    <label class="form-check-label fw-bold text-success" for="isSub">حساب فرعي قابل للتسجيل</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" :value="true" v-model="form.is_group" id="isGroup" />
                    <label class="form-check-label fw-bold text-primary" for="isGroup">حساب تجميعي رئيسي</label>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">الوصف / ملاحظات</label>
                <textarea v-model="form.description" class="form-control" rows="2" placeholder="وصف الحساب المحاسبي"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-primary fw-bold" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                {{ isEditing ? 'حفظ التعديلات' : 'إنشاء الحساب' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, defineComponent, h } from 'vue';
import axios from 'axios';
import * as bootstrap from 'bootstrap';


const AccountNode = defineComponent({
  name: 'AccountNode',
  props: ['node'],
  emits: ['add-child', 'edit-account', 'delete-account'],
  setup(props, { emit }) {
    const isExpanded = ref(true);
    const toggle = () => { isExpanded.value = !isExpanded.value; };

    const getTypeBadgeClass = (type) => {
      switch(type) {
        case 'ASSET': return 'bg-primary';
        case 'LIABILITY': return 'bg-warning text-dark';
        case 'EQUITY': return 'bg-info text-dark';
        case 'REVENUE': return 'bg-success';
        case 'EXPENSE': return 'bg-danger';
        default: return 'bg-secondary';
      }
    };

    return () => {
      const node = props.node;
      const hasChildren = node.children && node.children.length > 0;
      const linkedCurrencies = node.currencies || [];

      return h('div', { class: 'border-bottom py-2' }, [
        h('div', { class: 'd-flex align-items-center justify-content-between hover-bg p-1 rounded' }, [
          h('div', { class: 'd-flex align-items-center gap-2 flex-wrap' }, [
            hasChildren ? h('button', {
              class: 'btn btn-sm btn-link text-dark p-0 me-1 text-decoration-none',
              onClick: toggle
            }, h('i', { class: isExpanded.value ? 'bi bi-chevron-down' : 'bi bi-chevron-left' })) : h('span', { class: 'ms-3' }),
            h('span', { class: 'fw-bold text-primary' }, node.code),
            h('span', { class: node.is_group ? 'fw-bold' : '' }, node.name),
            h('span', { class: `badge ${getTypeBadgeClass(node.type)} ms-2` }, node.type),
            node.is_group
              ? h('span', { class: 'badge bg-light text-dark border ms-1' }, 'تجميعي')
              : h('span', { class: 'badge bg-success-subtle text-success ms-1' }, 'قابل للتسجيل'),
            linkedCurrencies.length > 0
              ? h('span', { class: 'badge bg-secondary-subtle text-secondary ms-1' }, 'عملات: ' + linkedCurrencies.map(c => c.code).join(', '))
              : null
          ]),

          h('div', { class: 'd-flex gap-1' }, [
            node.is_group ? h('button', {
              class: 'btn btn-sm btn-outline-primary py-0 px-2',
              onClick: () => emit('add-child', node),
              title: 'إضافة حساب فرعي'
            }, [h('i', { class: 'bi bi-plus-sm me-1' }), 'فرعي']) : null,

            h('button', {
              class: 'btn btn-sm btn-outline-secondary py-0 px-2',
              onClick: () => emit('edit-account', node),
              title: 'تعديل الحساب'
            }, [h('i', { class: 'bi bi-pencil me-1' }), 'تعديل']),

            !hasChildren ? h('button', {
              class: 'btn btn-sm btn-outline-danger py-0 px-1',
              onClick: () => emit('delete-account', node.id),
              title: 'حذف الحساب'
            }, h('i', { class: 'bi bi-trash' })) : null
          ])
        ]),
        hasChildren && isExpanded.value ? h('div', { class: 'ms-4 ps-2 border-start border-2 border-light' }, 
          node.children.map(child => h(AccountNode, {
            node: child,
            onAddChild: (n) => emit('add-child', n),
            onEditAccount: (n) => emit('edit-account', n),
            onDeleteAccount: (id) => emit('delete-account', id)
          }))
        ) : null
      ]);
    };
  }
});

const loading = ref(true);
const tree = ref([]);
const currencies = ref([]);
const parentAccount = ref(null);
const isEditing = ref(false);
const modalError = ref('');
const saving = ref(false);

const form = ref({
  id: null,
  code: '',
  name: '',
  type: 'ASSET',
  nature: 'DEBIT',
  is_group: false,
  is_postable: true,
  parent_id: null,
  currency_ids: [],
  description: '',
});

const fetchTree = async () => {
  loading.value = true;
  try {
    const [treeRes, currRes] = await Promise.all([
      axios.get('/api/v1/accounts?tree=true'),
      axios.get('/api/v1/currencies'),
    ]);
    if (treeRes.data.success) tree.value = treeRes.data.data;
    if (currRes.data.success) currencies.value = currRes.data.data;
  } catch (err) {
    console.error('Failed to load accounts:', err);
  } finally {
    loading.value = false;
  }
};

const getBaseCurrencyId = () => {
  const base = currencies.value.find(c => Number(c.exchange_rate) === 1.0 || c.code === 'SAR');
  return base ? Number(base.id) : (currencies.value.length > 0 ? Number(currencies.value[0].id) : null);
};

const openCreateModal = (parent) => {
  isEditing.value = false;
  parentAccount.value = parent;
  modalError.value = '';

  const baseId = getBaseCurrencyId();

  form.value = {
    id: null,
    code: parent ? parent.code + '1' : '',
    name: '',
    type: parent ? parent.type : 'ASSET',
    nature: parent ? parent.nature : 'DEBIT',
    is_group: false,
    is_postable: true,
    parent_id: parent ? parent.id : null,
    currency_ids: baseId ? [baseId] : [],
    description: '',
  };

  const modalEl = document.getElementById('accountModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const openEditModal = (node) => {
  isEditing.value = true;
  parentAccount.value = null;
  modalError.value = '';

  const baseId = getBaseCurrencyId();
  let ids = node.currencies ? node.currencies.map(c => Number(c.id)) : [];
  if (baseId && !ids.includes(baseId)) {
    ids.push(baseId);
  }

  form.value = {
    id: node.id,
    code: node.code,
    name: node.name,
    type: node.type,
    nature: node.nature,
    is_group: node.is_group,
    is_postable: node.is_postable,
    parent_id: node.parent_id || null,
    currency_ids: ids,
    description: node.description || '',
  };

  const modalEl = document.getElementById('accountModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const saveAccount = async () => {
  saving.value = true;
  modalError.value = '';
  try {
    form.value.is_postable = !form.value.is_group;

    const baseId = getBaseCurrencyId();
    let ids = (form.value.currency_ids || []).map(id => Number(id));
    if (baseId && !ids.includes(baseId)) {
      ids.push(baseId);
    }

    const payload = { 
      ...form.value,
      currency_ids: ids
    };
    if (!payload.parent_id) {
      delete payload.parent_id;
    }

    let res;
    if (isEditing.value && form.value.id) {
      res = await axios.put(`/api/v1/accounts/${form.value.id}`, payload);
    } else {
      res = await axios.post('/api/v1/accounts', payload);
    }

    if (res.data.success) {
      const modalEl = document.getElementById('accountModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      fetchTree();
    }
  } catch (err) {
    modalError.value = err.response?.data?.message || 'فشل حفظ التعديلات على الحساب';
  } finally {
    saving.value = false;
  }
};

const deleteAccount = async (id) => {
  if (!confirm('هل أنت تأكد من رغبتك في حذف هذا الحساب؟')) return;
  try {
    const res = await axios.delete(`/api/v1/accounts/${id}`);
    if (res.data.success) {
      alert('تم حذف الحساب بنجاح.');
      fetchTree();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'فشل حذف الحساب.');
  }
};

onMounted(() => {
  fetchTree();
});
</script>
