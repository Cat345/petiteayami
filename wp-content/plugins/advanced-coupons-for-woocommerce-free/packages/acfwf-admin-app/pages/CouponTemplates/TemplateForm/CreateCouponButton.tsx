// #region [Imports] ===================================================================================================

// Libraries
import { useState } from 'react';
import { Button } from 'antd';
import { useHistory } from 'react-router-dom';
import { SizeType } from 'antd/lib/config-provider/SizeContext';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';

// Types
import { IStore } from '../../../types/store';
import { ICouponTemplateFormData, ICreateCouponFromTemplateResponse } from '../../../types/couponTemplates';

// Actions
import { CouponTemplatesActions } from '../../../store/actions/couponTemplates';
import { ICouponTemplate } from '../../../types/couponTemplates';

// #endregion [Imports]

// #region [Variables] =================================================================================================

const {
  validateEditCouponTemplateData,
  validateCartConditionsData,
  createCouponFromTemplate,
  setCreatedCouponResponseData,
} = CouponTemplatesActions;

// #endregion [Variables]

// #region [Interfaces]=================================================================================================

interface IActions {
  validateEditCouponTemplateData: typeof validateEditCouponTemplateData;
  validateCartConditionsData: typeof validateCartConditionsData;
  createCouponFromTemplate: typeof createCouponFromTemplate;
  setCreatedCouponResponseData: typeof setCreatedCouponResponseData;
}

interface IProps {
  template: ICouponTemplate | null;
  text: string;
  size: SizeType;
  disabled: boolean;
  actions: IActions;
}

// #endregion [Interfaces]

// #region [Component] =================================================================================================

const CreateCouponButton = (props: IProps) => {
  const { template, text, size, disabled, actions } = props;
  const [isLoading, setIsLoading] = useState(false);

  const handleCreateCoupon = () => {
    actions.validateEditCouponTemplateData();
    actions.validateCartConditionsData();

    // Don't proceed when there are fields with errors.
    const errors = template?.fields?.filter((field) => field.error);
    if (!template || errors?.length) {
      return;
    }

    const data: ICouponTemplateFormData = {
      id: template.id,
      fields: template.fields.map((field) => ({
        key: field.field,
        value: field.value,
        type: field.fixtures.type,
      })),
      cart_conditions: template.cart_conditions ?? [],
    };

    setIsLoading(true);
    actions.createCouponFromTemplate({
      data: data,
      successCB: (response: any) => {
        setIsLoading(false);
        actions.setCreatedCouponResponseData({ data: response.data as ICreateCouponFromTemplateResponse });
      },
    });
  };

  return (
    <Button loading={isLoading} type="primary" onClick={handleCreateCoupon} size={size} disabled={disabled}>
      {text}
    </Button>
  );
};

const mapStateToProps = (state: IStore) => ({
  template: state.couponTemplates?.edit ?? null,
  formResponse: state.couponTemplates?.formResponse ?? null,
});

const mapDispatchToProps = (dispatch: any) => ({
  actions: bindActionCreators(
    {
      validateEditCouponTemplateData,
      validateCartConditionsData,
      createCouponFromTemplate,
      setCreatedCouponResponseData,
    },
    dispatch
  ),
});

export default connect(mapStateToProps, mapDispatchToProps)(CreateCouponButton);

// #endregion [Component]
