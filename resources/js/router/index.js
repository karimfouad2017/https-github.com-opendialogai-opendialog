import Vue from 'vue'
import VueRouter from 'vue-router'
// Containers
import DefaultContainer from '@/containers/DefaultContainer'

import store from '@opendialogai/opendialog-design-system-pkg/src/store'

import Home from '@opendialogai/opendialog-design-system-pkg/src/components/Views/Home'
import WebchatSettingView from '@opendialogai/opendialog-design-system-pkg/src/components/Views/WebchatSettingView'
import MessageEditor from '@opendialogai/opendialog-design-system-pkg/src/components/Views/MessageEditor'
import ChatbotUsersView from '@opendialogai/opendialog-design-system-pkg/src/components/Views/ChatbotUsersView'
import UserView from '@opendialogai/opendialog-design-system-pkg/src/components/Views/UserView'
import RequestView from '@opendialogai/opendialog-design-system-pkg/src/components/Views/RequestView'
import GlobalContextView from '@opendialogai/opendialog-design-system-pkg/src/components/Views/GlobalContextView'
import WarningView from '@opendialogai/opendialog-design-system-pkg/src/components/Views/WarningView'
import WebchatDemo from '@opendialogai/opendialog-design-system-pkg/src/components/Views/WebchatDemo'
import ConversationLog from '@opendialogai/opendialog-design-system-pkg/src/components/Views/ConversationLog'
import DynamicAttribute from '@/views/DynamicAttribute'
import Scenarios
  from '@opendialogai/opendialog-design-system-pkg/src/components/Scenarios/Scenarios'
import CreateNewScenario
  from '@opendialogai/opendialog-design-system-pkg/src/components/Scenarios/CreateNewScenario'
import ConversationBuilder
  from '@opendialogai/opendialog-design-system-pkg/src/components/ConversationBuilder/Wrapper/ConversationBuilder'
import Interpreters
  from '@opendialogai/opendialog-design-system-pkg/src/components/Interpreters/Interpreters'
import ConfigureInterpreter
  from '@opendialogai/opendialog-design-system-pkg/src/components/Interpreters/ConfigureInterpreter'
import MapInterpreter
from '@opendialogai/opendialog-design-system-pkg/src/components/Interpreters/MapInterpreter'
import EditInterpreter
from '@opendialogai/opendialog-design-system-pkg/src/components/Interpreters/EditInterpreter'
import Actions
from '@opendialogai/opendialog-design-system-pkg/src/components/Actions/Actions'
import ConfigureAction
from '@opendialogai/opendialog-design-system-pkg/src/components/Actions/ConfigureAction'
import Template
  from '@opendialogai/opendialog-design-system-pkg/src/components/Scenarios/Template'


Vue.use(VueRouter);


const router = new VueRouter({
  mode: 'history',
  routes: [
    {
      path: '/admin',
      component: DefaultContainer,
      children: [
        {
          path: '/',
          name: 'scenarios',
          component: Scenarios,
          props: route => ({ newScenario: route.query.newScenario === "true" }),
          meta: {
            title: 'Workspace',
            sidebarLabel: 'All scenarios'
          },
        },
        {
          path: 'create-new-scenario',
          name: 'create-scenario',
          component: CreateNewScenario,
          meta: {
            title: 'Create New Scenario',
            sidebarLabel: 'Create scenario',
            sidebarIcon: 'od-icon-plus-2'
          },
        },
        {
          path: 'template/:id',
          name: 'template',
          component: Template,
          meta: {
            title: 'Templates',
          },
        },
        {
          path: 'conversation-builder/*',
          name: 'conversation-builder',
          component: ConversationBuilder,
          props: route => ({ newScenario: route.query.newScenario }),
          meta: {
            title: 'Conversation Designer',
            requiresScenario: true
          },
        },
        {
          path: 'actions',
          name: 'actions',
          component: Actions,
          meta: {
            title: 'Actions',
            requiresScenario: true
          },
        },
        {
          path: 'actions/configure/:id',
          name: 'configure-action',
          component: ConfigureAction,
          meta: {
            title: 'Actions',
            requiresScenario: true
          },
        },
        {
          path: 'interpreters',
          name: 'interpreters',
          component: Interpreters,
          meta: {
            title: 'Interpreters',
            requiresScenario: true
          },
        },
        {
          path: 'message-editor',
          name: 'message-editor',
          component: MessageEditor,
          meta: {
            title: 'Message Editor',
            requiresScenario: true
          },
        },
        {
          path: 'interpreters/configure/new',
          name: 'configure-interpreter',
          component: ConfigureInterpreter,
          meta: {
            title: 'Configure Interpreter',
            requiresScenario: true
          },
        },
        {
          path: 'interpreters/configure/:id',
          name: 'edit-interpreter',
          component: EditInterpreter,
          meta: {
            title: 'Configure Interpreter',
            requiresScenario: true
          },
        },
        {
          path: 'interpreters/mapping/:id',
          name: 'map-interpreter',
          component: MapInterpreter,
          meta: {
            title: 'Configure Interpreter',
            requiresScenario: true
          },
        },
        {
          path: 'webchat-setting',
          name: 'webchat-setting',
          component: WebchatSettingView,
          meta: {
            title: 'Interface Settings',
            requiresScenario: true
          },
        },
        {
          path: 'chatbot-users',
          name: 'chatbot-users',
          component: ChatbotUsersView,
          meta: {
            title: 'Chatbot Users',
          },
        },
        {
          path: 'chatbot-users/:id',
          name: 'view-chatbot-user',
          component: ChatbotUsersView,
          meta: {
            title: 'Chatbot Users',
          },
          props: true,
        },
        {
          path: 'chatbot-users/:id/conversation-log',
          name: 'conversation-log',
          component: ConversationLog,
          meta: {
            title: 'Conversation Log',
          },
          props: true,
        },
        {
          path: 'dynamic-attributes',
          name: 'dynamic-attributes',
          component: DynamicAttribute,
          meta: {
            title: 'Dynamic Attributes',
          },
        },
        {
          path: 'dynamic-attributes/add',
          name: 'add-dynamic-attribute',
          component: DynamicAttribute,
          meta: {
            title: 'Dynamic Attributes',
          },
        },
        {
          path: 'dynamic-attributes/:id',
          name: 'view-dynamic-attribute',
          component: DynamicAttribute,
          meta: {
            title: 'Dynamic Attributes',
          },
          props: true,
      },
      {
          path: 'dynamic-attributes/:id/edit',
          name: 'edit-dynamic-attribute',
          component: DynamicAttribute,
          meta: {
            title: 'Dynamic Attributes',
          },
          props: true,
      },
        {
          path: 'users',
          name: 'users',
          component: UserView,
          meta: {
            title: 'Users',
          },
        },
        {
          path: 'users/add',
          name: 'add-user',
          component: UserView,
          meta: {
            title: 'Add User',
          },
        },
        {
          path: 'users/:id',
          name: 'view-user',
          component: UserView,
          meta: {
            title: 'Account',
          },
          props: true,
        },
        {
          path: 'users/:id/edit',
          name: 'edit-user',
          component: UserView,
          meta: {
            title: 'Account',
          },
          props: true,
        },
        {
          path: 'requests',
          name: 'requests',
          component: RequestView,
          meta: {
            title: 'Requests',
          },
        },
        {
          path: 'requests/:id',
          name: 'view-request',
          component: RequestView,
          meta: {
            title: 'Requests',
          },
          props: true,
        },
        {
          path: 'global-contexts/',
          name: 'global-contexts',
          component: GlobalContextView,
          meta: {
            title: 'Global Contexts',
          },
        },
        {
          path: 'global-contexts/add',
          name: 'add-global-context',
          component: GlobalContextView,
          meta: {
            title: 'Global Contexts',
          },
        },
        {
          path: 'global-contexts/:id',
          name: 'view-global-context',
          component: GlobalContextView,
          meta: {
            title: 'Global Contexts',
          },
          props: true,
        },
        {
          path: 'global-contexts/:id/edit',
          name: 'edit-global-context',
          component: GlobalContextView,
          meta: {
            title: 'Global Contexts',
          },
          props: true,
        },
        {
          path: 'warnings',
          name: 'warnings',
          component: WarningView,
          meta: {
            title: 'Warnings',
          },
        },
        {
          path: 'warnings/:id',
          name: 'view-warning',
          component: WarningView,
          meta: {
            title: 'Warnings',
          },
          props: true,
        },
        {
          path: 'demo',
          name: 'webchat-demo',
          component: WebchatDemo,
          meta: {
            title: 'Preview',
            requiresScenario: true
          }
        }
      ],
    },
  ],
});

router.beforeEach(async (to, from, next) => {
  if (store.state.hasScenarios === null) {
    await store.dispatch('fetchScenarios').then(hasScenarios => {
      if (!hasScenarios && (to.path === '/admin' || to.meta.requiresScenario)) {
        next('/admin/create-new-scenario')
      }
    }).catch(err => {
      next('/admin/create-new-scenario')
    })
  } else if (store.state.hasScenarios === false && to.path !== '/admin/create-new-scenario' && to.meta.requiresScenario) {
    next('/admin/create-new-scenario')
  }

  if (to.path === '/admin' || to.path === '/admin/create-new-scenario') {
    store.commit('updateSelectedScenario', {name: null, id: null})
  }
  
  if (to.query.scenario && !store.state.selectedScenario.id) {
    store.dispatch('fetchScenario', to.query.scenario).then(() => {
      store.commit('initialScenarioLoaded', true)
    }).catch(() => {
      next('/admin')
    })
  } else if (!store.state.initialScenarioLoaded) {
    store.commit('initialScenarioLoaded', true)
  }

  const scenario = to.query.scenario ? to.query.scenario : store.state.selectedScenario.id
  if (!scenario && (to.meta.requiresScenario)) {
    next('/admin')
  } else {
    if (scenario && !to.query.scenario && (to.meta.requiresScenario)) {
      next({path: to.path, query: {...to.query, scenario: scenario}})
    } else {
      next()
    }
  }
})

export default router;
