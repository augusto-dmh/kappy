import { Model, DataTypes } from "sequelize";
import { hashSync } from "bcryptjs";
import * as validations from "../validation";
import * as errors from "../validation/errors";
import appConfig from "../config/appConfig";

export default class Avatar extends Model {
  static init(sequelize) {
    super.init(
      {
        name: {
          type: DataTypes.STRING,
          defaultValue: "",
          unique: {
            msg: errors.models.name.inUse,
          },
          validate: {
            custom(value) {
              if (!validations.isNotEmpty(value)) {
                throw errors.models.name.empty;
              }
              if (!validations.isLengthValid(value, 0, 20)) {
                throw errors.models.name.invalidLength;
              }
            },
          },
        },
        filename: {
          type: DataTypes.STRING,
          defaultValue: "",
          validate: {
            custom(value) {
              if (!validations.isNotEmpty(value)) {
                throw errors.models.filename.empty;
              }
            },
          },
        },
        url: {
          type: DataTypes.VIRTUAL,
          get() {
            return `${appConfig.url}/images/${this.getDataValue("filename")}`;
          },
        },
      },
      {
        sequelize,
        modelName: "avatar",
      }
    );

    return this;
  }

  static associate(models) {
    this.belongsToMany(models.user, {
      foreignKey: "avatarId",
      through: "user_avatars",
    });
  }
}
