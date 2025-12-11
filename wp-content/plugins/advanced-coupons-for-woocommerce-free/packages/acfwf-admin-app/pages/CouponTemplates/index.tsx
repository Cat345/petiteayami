// #region [Imports] ===================================================================================================

// Libraries
import { useEffect } from 'react';
import { useHistory, useLocation } from 'react-router-dom';
import { Row, Col, Tabs, Modal, Button } from 'antd';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

// Components
import AdminHeader from '../../components/AdminHeader';
import RecentTemplates from './RecentTemplates';
import QueriedTemplates from './QueriedTemplates';
import ReviewTemplates from './ReviewTemplates';
import Sidebar from './Sidebar';
import TemplateForm from './TemplateForm';
import Logo from '../../components/Logo';

// Actions
import { CouponTemplatesActions } from '../../store/actions/couponTemplates';

// Types
import { IStore } from '../../types/store';

// Helpers
import { getPathPrefix } from '../../helpers/utils';

// SCSS
import './index.scss';

// #endregion [Imports]

// #region [Variables] =================================================================================================

declare var acfwAdminApp: any;

const { togglePremiumModal } = CouponTemplatesActions;

// #endregion [Variables]

// #region [Interfaces]=================================================================================================

interface IActions {
  togglePremiumModal: typeof togglePremiumModal;
}

interface IProps {
  showModal: boolean;
  actions: IActions;
}

// #endregion [Interfaces]

// #region [Component] =================================================================================================

const CouponTemplates = (props: IProps) => {
  const { showModal, actions } = props;
  const { title, labels, enable_review_tab } = acfwAdminApp.coupon_templates_page;
  const history = useHistory();
  const location = useLocation();
  const urlParams = new URLSearchParams(location.search);
  const editId = urlParams.get('id') ?? null;
  const pathPrefix = getPathPrefix();
  const currentTab = urlParams.get('cttab') ?? 'main';

  const handleTabClick = (key: string) => {
    history.push(`${pathPrefix}admin.php?page=acfw-coupon-templates&cttab=${key}`);
  };

  return (
    <div className="coupon-templates-page">
      <AdminHeader title={title} className="coupon-templates-header" />
      {editId ? (
        <TemplateForm />
      ) : (
        <Tabs defaultActiveKey={currentTab} className="coupon-templates-tabs" onTabClick={handleTabClick}>
          <Tabs.TabPane tab={labels.recently_used_templates} key="recent">
            <Row gutter={16}>
              <Col xs={24} sm={24} md={18} lg={18} xl={18}>
                <RecentTemplates />
              </Col>
            </Row>
          </Tabs.TabPane>
          <Tabs.TabPane tab={labels.available_templates} key="main">
            <Row gutter={16}>
              <Col xs={24} sm={24} md={18} lg={18} xl={18}>
                <QueriedTemplates />
              </Col>
              <Col xs={24} sm={24} md={6} lg={6} xl={6}>
                <Sidebar />
              </Col>
            </Row>
          </Tabs.TabPane>
          {enable_review_tab && (
            <Tabs.TabPane tab={labels.review_templates} key="review">
              <Row gutter={16}>
                <Col xs={24} sm={24} md={18} lg={18} xl={18}>
                  <ReviewTemplates />
                </Col>
              </Row>
            </Tabs.TabPane>
          )}
        </Tabs>
      )}
      <Modal
        className="coupon-templates-premium-modal"
        open={showModal}
        centered
        onCancel={() => actions.togglePremiumModal({ show: false })}
        footer={null}
      >
        <Logo hideUpgrade />
        <p>{labels.premium_modal_text}</p>
        <Button type="primary" href={acfwAdminApp.coupon_templates_page.link} size="large" target="_blank">
          {labels.premium_modal_btn}
        </Button>
      </Modal>
    </div>
  );
};

const mapStateToProps = (state: IStore) => ({
  showModal: state.couponTemplates?.premiumModal ?? false,
});

const mapDispatchToProps = (dispatch: any) => ({
  actions: bindActionCreators({ togglePremiumModal }, dispatch),
});

export default connect(mapStateToProps, mapDispatchToProps)(CouponTemplates);

// #endregion [Component]
